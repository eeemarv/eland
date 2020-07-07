<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Cnst\StatusCnst;
use App\Cnst\RoleCnst;
use App\Cnst\BulkCnst;
use App\HtmlProcess\HtmlPurifier;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\SelectRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\CacheService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\ThumbprintAccountsService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class UsersListController extends AbstractController
{
    public function __invoke(
        Request $request,
        string $status,
        Db $db,
        LoggerInterface $logger,
        SessionInterface $session,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        CacheService $cache_service,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        SelectRender $select_render,
        ThumbprintAccountsService $thumbprint_accounts_service,
        TypeaheadService $typeahead_service,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MenuService $menu_service,
        HtmlPurifier $html_purifier
    ):Response
    {
        $errors = [];

        $q = $request->get('q', '');
        $show_columns = $request->query->get('sh', []);

        $selected_users = $request->request->get('sel', []);
        $bulk_mail_subject = $request->request->get('bulk_mail_subject', '');
        $bulk_mail_content = $request->request->get('bulk_mail_content', '');
        $bulk_mail_cc = $request->request->has('bulk_mail_cc');
        $bulk_field = $request->request->get('bulk_field', []);
        $bulk_verify = $request->request->get('bulk_verify', []);
        $bulk_submit = $request->request->get('bulk_submit', []);

        /**
         * Begin bulk POST
         */

        if ($pp->is_admin()
            && $request->isMethod('POST')
            && count($bulk_submit) === 1)
        {
            if (count($bulk_field) > 1)
            {
                throw new BadRequestHttpException('Ongeldig formulier. Request voor meer dan één veld.');
            }

            if (count($bulk_verify) > 1)
            {
                throw new BadRequestHttpException('Ongeldig formulier. Meer dan één bevestigingsvakje.');
            }

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (count($bulk_verify) !== 1)
            {
                $errors[] = 'Het controle nazichts-vakje is niet aangevinkt.';
            }

            $bulk_submit_action = array_key_first($bulk_submit);
            $bulk_verify_action = array_key_first($bulk_verify);
            $bulk_field_action = array_key_first($bulk_field);

            if (isset($bulk_verify_action)
                && !($bulk_verify_action === 'mail' && $bulk_submit_action === 'mail_test')
                && $bulk_verify_action !== $bulk_submit_action)
            {
                throw new BadRequestHttpException('Ongeldig formulier. Actie nazichtvakje klopt niet.');
            }

            if (isset($bulk_field_action)
                && $bulk_field_action !== $bulk_submit_action)
            {
                throw new BadRequestHttpException('Ongeldig formulier. Actie waardeveld klopt niet.');
            }

            if (!in_array($bulk_submit_action, ['periodic_overview_en', 'mail', 'mail_test'])
                && !isset($bulk_field_action))
            {
                throw new BadRequestHttpException('Ongeldig formulier. Waarde veld ontbreekt.');
            }

            if (in_array($bulk_submit_action, ['periodic_overview_en', 'mail', 'mail_test']))
            {
                $bulk_field_value = isset($bulk_field[$bulk_submit_action]);
            }
            else
            {
                $bulk_field_value = $bulk_field[$bulk_field_action];
            }

            if (in_array($bulk_submit_action, ['mail', 'mail_test']))
            {
                if (!$config_service->get('mailenabled', $pp->schema()))
                {
                    $errors[] = 'De E-mail functies zijn niet ingeschakeld. Zie instellingen.';
                }

                if ($su->is_master())
                {
                    $errors[] = 'Het master account kan geen E-mail berichten verzenden.';
                }

                if (!$bulk_mail_subject)
                {
                    $errors[] = 'Vul een onderwerp in voor je E-mail.';
                }

                if (!$bulk_mail_content)
                {
                    $errors[] = 'Het E-mail bericht is leeg.';
                }
            }
            else if (strpos($bulk_submit_action, '_access') !== false)
            {
                if (!$bulk_field_value)
                {
                    $errors[] = 'Vul een zichtbaarheid in.';
                }
            }

            if (!count($selected_users) && $bulk_submit_action !== 'mail_test')
            {
                $errors[] = 'Selecteer ten minste één gebruiker voor deze actie.';
            }

            if (count($errors))
            {
                $alert_service->error($errors);
            }
            else
            {
                $user_ids = array_keys($selected_users);

                $users_log = '';

                $rows = $db->executeQuery('select code, name, id
                    from ' . $pp->schema() . '.users
                    where id in (?)',
                    [$user_ids], [Db::PARAM_INT_ARRAY]);

                foreach ($rows as $row)
                {
                    $users_log .= ', ';
                    $users_log .= $account_render->str_id($row['id'], $pp->schema(), false, true);
                }

                $users_log = ltrim($users_log, ', ');
            }

            $redirect = false;

            $user_tab_data = BulkCnst::USER_TABS[$bulk_submit_action] ?? [];

            if (!count($errors)
                && isset($user_tab_data['contact_abbrev'])
                && isset($user_tab_data['item_access']))
            {
                $abbrev = $user_tab_data['contact_abbrev'];

                $id_type_contact = $db->fetchColumn('select id
                    from ' . $pp->schema() . '.type_contact
                    where abbrev = ?', [$abbrev]);

                $db->executeUpdate('update ' . $pp->schema() . '.contact
                    set access = ?
                    where user_id in (?) and id_type_contact = ?',
                        [$bulk_field_value, $user_ids, $id_type_contact],
                        [\PDO::PARAM_STR, Db::PARAM_INT_ARRAY, \PDO::PARAM_INT]);

                $logger->info('bulk: Set ' . $bulk_field_action .
                    ' to ' . $bulk_field_value .
                    ' for users ' . $users_log,
                    ['schema' => $pp->schema()]);
                $alert_service->success('Het veld werd aangepast.');

                $redirect = true;
            }
            else if (!count($errors)
                && $bulk_submit_action === 'periodic_overview_en')
            {
                $db->executeUpdate('update ' . $pp->schema() . '.users
                    set periodic_overview_en = ?
                    where id in (?)',
                    [$bulk_field_value, $user_ids],
                    [\PDO::PARAM_BOOL, Db::PARAM_INT_ARRAY]);

                foreach ($user_ids as $user_id)
                {
                    $user_cache_service->clear($user_id, $pp->schema());
                }

                $log_value = $bulk_field_value ? 'on' : 'off';

                $logger->info('bulk: Set periodic mail to ' .
                    $log_value . ' for users ' .
                    $users_log,
                    ['schema' => $pp->schema()]);

                $intersystems_service->clear_cache($su->schema());

                $alert_service->success('Het veld werd aangepast.');

                $redirect = true;
            }
            else if (!count($errors)
                && $user_tab_data)
            {
                $store_value = $bulk_field_value;

                if (in_array($bulk_submit_action, ['minlimit', 'maxlimit']))
                {
                    $store_value = $store_value === '' ? null : $store_value;
                }

                $field_type = isset($user_tab_data['string']) ? \PDO::PARAM_STR : \PDO::PARAM_INT;

                $db->executeUpdate('update ' . $pp->schema() . '.users
                    set ' . $bulk_submit_action . ' = ? where id in (?)',
                    [$store_value, $user_ids],
                    [$field_type, Db::PARAM_INT_ARRAY]);

                foreach ($user_ids as $user_id)
                {
                    $user_cache_service->clear($user_id, $pp->schema());
                }

                if ($bulk_field == 'status')
                {
                    $thumbprint_accounts_service->delete('active', $pp->ary(), $pp->schema());
                    $thumbprint_accounts_service->delete('extern', $pp->ary(), $pp->schema());
                }

                $logger->info('bulk: Set ' . $bulk_submit_action .
                    ' to ' . $store_value .
                    ' for users ' . $users_log,
                    ['schema' => $pp->schema()]);

                $intersystems_service->clear_cache($pp->schema());

                $alert_service->success('Het veld werd aangepast.');

                $redirect = true;
            }
            else if (!count($errors)
                && in_array($bulk_submit_action, ['mail', 'mail_test']))

            {
                if ($bulk_submit_action === 'mail_test')
                {
                    $sel_ary = [$su->id() => true];
                    $user_ids = [$su->id()];
                }
                else
                {
                    $sel_ary = $selected_users;
                }

                $alert_users_sent_ary = [];
                $mail_users_sent_ary = [];
                $sent_to_ary = [];

                $bulk_mail_content = $html_purifier->purify($bulk_mail_content);

                $sel_users = $db->executeQuery('select u.*, c.value as mail
                    from ' . $pp->schema() . '.users u, ' .
                        $pp->schema() . '.contact c, ' .
                        $pp->schema() . '.type_contact tc
                    where u.id in (?)
                        and u.id = c.user_id
                        and c.id_type_contact = tc.id
                        and tc.abbrev = \'mail\'',
                        [$user_ids], [Db::PARAM_INT_ARRAY]);

                foreach ($sel_users as $sel_user)
                {
                    if (!isset($sel_ary[$sel_user['id']]))
                    {
                        // avoid duplicate send when multiple mail addresses for one user.
                        continue;
                    }

                    unset($sel_ary[$sel_user['id']]);

                    $vars = [
                        'subject'	=> $bulk_mail_subject,
                    ];

                    foreach (BulkCnst::USER_TPL_VARS as $key => $val)
                    {
                        $vars[$key] = $sel_user[$val];
                    }

                    $mail_queue->queue([
                        'schema'			=> $pp->schema(),
                        'to' 				=> $mail_addr_user_service->get($sel_user['id'], $pp->schema()),
                        'pre_html_template' => $bulk_mail_content,
                        'reply_to' 			=> $mail_addr_user_service->get($su->id(), $pp->schema()),
                        'vars'				=> $vars,
                        'template'			=> 'skeleton',
                    ], random_int(200, 2000));

                    $sent_to_ary[] = (int) $sel_user['id'];
                    $alert_users_sent_ary[] = $account_render->link($sel_user['id'], $pp->ary());
                    $mail_users_sent_ary[] = $account_render->link_url($sel_user['id'], $pp->ary());
                }

                if (count($alert_users_sent_ary))
                {
                    if ($bulk_submit_action === 'mail')
                    {
                        $db->insert($pp->schema() . '.emails', [
                            'subject'       => $bulk_mail_subject,
                            'content'       => $bulk_mail_content,
                            'route'         => $request->attributes->get('_route'),
                            'sent_to'       => json_encode($sent_to_ary),
                            'created_by'    => $su->id(),
                        ]);
                    }

                    $msg_users_sent = 'E-mail verzonden naar ';
                    $msg_users_sent .= count($alert_users_sent_ary);
                    $msg_users_sent .= ' ';
                    $msg_users_sent .= count($alert_users_sent_ary) > 1 ? 'accounts' : 'account';
                    $msg_users_sent .= ':';
                    $alert_users_sent = $msg_users_sent . '<br>';
                    $alert_users_sent .= implode('<br>', $alert_users_sent_ary);

                    $alert_service->success($alert_users_sent);
                }
                else
                {
                    $alert_service->warning('Geen E-mails verzonden.');
                }

                if (count($sel_ary))
                {
                    $msg_missing_users = 'Naar volgende gebruikers werd geen
                        E-mail verzonden wegens ontbreken van E-mail adres:';

                    $alert_missing_users = $msg_missing_users . '<br>';
                    $mail_missing_users = $msg_missing_users . '<br />';

                    foreach ($sel_ary as $warning_user_id => $dummy)
                    {
                        $alert_missing_users .= $account_render->link($warning_user_id, $pp->ary());
                        $alert_missing_users .= '<br>';

                        $mail_missing_users .= $account_render->link_url($warning_user_id, $pp->ary());
                        $mail_missing_users .= '<br />';
                    }

                    $alert_service->warning($alert_missing_users);
                }

                if ($bulk_mail_cc)
                {
                    $vars = [
                        'subject'	=> 'Kopie: ' . $bulk_mail_subject,
                    ];

                    foreach (BulkCnst::USER_TPL_VARS as $key => $trans)
                    {
                        $vars[$key] = '{{ ' . $key . ' }}';
                    }

                    $mail_users_info = $msg_users_sent . '<br />';
                    $mail_users_info .= implode('<br />', $alert_users_sent_ary);
                    $mail_users_info .= '<br /><br />';

                    if (isset($mail_missing_users))
                    {
                        $mail_users_info .= $mail_missing_users;
                        $mail_users_info .= '<br/>';
                    }

                    $mail_users_info .= '<hr /><br />';

                    $mail_queue->queue([
                        'schema'			=> $pp->schema(),
                        'to' 				=> $mail_addr_user_service->get($su->id(), $pp->schema()),
                        'template'			=> 'skeleton',
                        'pre_html_template'	=> $mail_users_info . $bulk_mail_content,
                        'vars'				=> $vars,
                    ], 8000);

                    $logger->debug('#bulk mail:: ' .
                        $mail_users_info . $bulk_mail_content,
                        ['schema' => $pp->schema()]);
                }

                if ($bulk_submit_action === 'mail')
                {
                    $redirect = true;
                }
            }

            if ($redirect)
            {
                $link_render->redirect($vr->get('users'), $pp->ary(), []);
            }
        }

        /**
         * End bulk POST
         */

        $status_def_ary = self::get_status_def_ary($config_service, $pp);

        $sql_bind = [];

        if (isset($status_def_ary[$status]['sql_bind']))
        {
            $sql_bind[] = $status_def_ary[$status]['sql_bind'];
        }

        $params = ['status'	=> $status];

        $ref_geo = [];

        $type_contact = $db->fetchAll('select id, abbrev, name
            from ' . $pp->schema() . '.type_contact');

        $columns = [
            'u'		=> [
                'code'		    => 'Code',
                'name'			=> 'Naam',
                'fullname'		=> 'Volledige naam',
                'postcode'		=> 'Postcode',
                'role'	        => 'Rol',
                'balance'		=> 'Saldo',
                'balance_date'	=> 'Saldo op ',
                'minlimit'		=> 'Min',
                'maxlimit'		=> 'Max',
                'comments'		=> 'Commentaar',
                'hobbies'		=> 'Hobbies/interesses',
            ],
        ];

        if ($pp->is_admin())
        {
            $columns['u'] += [
                'admincomment'	        => 'Admin commentaar',
                'periodic_overview_en'	=> 'Periodieke Overzichts E-mail',
                'created_at'	        => 'Gecreëerd',
                'last_edit_at'	        => 'Aangepast',
                'adate'			        => 'Geactiveerd',
                'last_login'		    => 'Laatst ingelogd',
            ];
        }

        foreach ($type_contact as $tc)
        {
            $columns['c'][$tc['abbrev']] = $tc['name'];
        }

        $columns['d'] = [
            'distance'	=> 'Afstand',
        ];

        $columns['m'] = [
            'wants'		=> 'Vraag',
            'offers'	=> 'Aanbod',
            'total'		=> 'Vraag en aanbod',
        ];

        $message_type_filter = [
            'wants'		=> ['want' => 'on'],
            'offers'	=> ['offer' => 'on'],
            'total'		=> '',
        ];

        $columns['a'] = [
            'trans'		=> [
                'in'	=> 'Transacties in',
                'out'	=> 'Transacties uit',
                'total'	=> 'Transacties totaal',
            ],
            'amount'	=> [
                'in'	=> $config_service->get('currency', $pp->schema()) . ' in',
                'out'	=> $config_service->get('currency', $pp->schema()) . ' uit',
                'total'	=> $config_service->get('currency', $pp->schema()) . ' totaal',
            ],
        ];

        $columns['p'] = [
            'c'	=> [
                'adr_split'	=> '.',
            ],
            'a'	=> [
                'days'	=> '.',
                'code'	=> '.',
            ],
            'u'	=> [
                'balance_date'	=> '.',
            ],
        ];

        $session_users_columns_key = 'users_columns_';
        $session_users_columns_key .= $pp->role();

        if (count($show_columns))
        {
            $show_columns = self::array_intersect_key_recursive($show_columns, $columns);

            $session->set($session_users_columns_key, $show_columns);
        }
        else
        {
            if ($pp->is_admin() || $pp->is_guest())
            {
                $preset_columns = [
                    'u'	=> [
                        'code'	=> 1,
                        'name'		=> 1,
                        'postcode'	=> 1,
                        'balance'		=> 1,
                    ],
                ];
            }
            else
            {
                $preset_columns = [
                    'u' => [
                        'code'	=> 1,
                        'name'		=> 1,
                        'postcode'	=> 1,
                        'balance'		=> 1,
                    ],
                    'c'	=> [
                        'gsm'	=> 1,
                        'tel'	=> 1,
                        'adr'	=> 1,
                    ],
                    'd'	=> [
                        'distance'	=> 1,
                    ],
                ];
            }

            $show_columns = $session->get($session_users_columns_key) ?? $preset_columns;
        }

        $adr_split = $show_columns['p']['c']['adr_split'] ?? '';
        $activity_days = $show_columns['p']['a']['days'] ?? 365;
        $activity_days = $activity_days < 1 ? 365 : $activity_days;
        $activity_filter_code = $show_columns['p']['a']['code'] ?? '';
        $balance_date = $show_columns['p']['u']['balance_date'] ?? '';
        $balance_date = trim($balance_date);

        $users = $db->fetchAll('select u.*
            from ' . $pp->schema() . '.users u
            where ' . $status_def_ary[$status]['sql'] . '
            order by u.code asc', $sql_bind);

        if (isset($show_columns['u']['balance_date']))
        {
            if ($balance_date)
            {
                $balance_date_rev = $date_format_service->reverse($balance_date, 'min', $pp->schema());
            }

            if ($balance_date_rev === '' || $balance_date == '')
            {
                $balance_date = $date_format_service->get('', 'day', $pp->schema());

                array_walk($users, function(&$user, $user_id){
                    $user['balance_date'] = $user['balance'];
                });
            }
            else
            {
                $trans_in = $trans_out = [];
                $datetime = new \DateTime($balance_date_rev);

                $rs = $db->prepare('select id_to, sum(amount)
                    from ' . $pp->schema() . '.transactions
                    where date <= ?
                    group by id_to');

                $rs->bindValue(1, $datetime, 'datetime');

                $rs->execute();

                while($row = $rs->fetch())
                {
                    $trans_in[$row['id_to']] = $row['sum'];
                }

                $rs = $db->prepare('select id_from, sum(amount)
                    from ' . $pp->schema() . '.transactions
                    where date <= ?
                    group by id_from');
                $rs->bindValue(1, $datetime, 'datetime');

                $rs->execute();

                while($row = $rs->fetch())
                {
                    $trans_out[$row['id_from']] = $row['sum'];
                }

                array_walk($users, function(&$user) use ($trans_out, $trans_in){
                    $user['balance_date'] = 0;
                    $user['balance_date'] += $trans_in[$user['id']] ?? 0;
                    $user['balance_date'] -= $trans_out[$user['id']] ?? 0;
                });
            }
        }

        if (isset($show_columns['u']['last_login']))
        {
            $last_login_ary = [];

            $stmt = $db->executeQuery('select user_id, max(created_at) as last_login
                from ' . $pp->schema() . '.login
                group by user_id');

            while ($row = $stmt->fetch())
            {
                $last_login_ary[$row['user_id']] = $row['last_login'];
            }

            array_walk($users, function(&$user) use ($last_login_ary){
                $user['last_login'] = $last_login_ary[$user['id']] ?? '';
            });
        }

        if (isset($show_columns['c']) || (isset($show_columns['d']) && !$su->is_master()))
        {
            $c_ary = $db->fetchAll('select tc.abbrev,
                    c.user_id, c.value, c.access
                from ' . $pp->schema() . '.contact c, ' .
                    $pp->schema() . '.type_contact tc, ' .
                    $pp->schema() . '.users u
                where tc.id = c.id_type_contact ' .
                    (isset($show_columns['c']) ? '' : 'and tc.abbrev = \'adr\' ') .
                    'and c.user_id = u.id
                    and ' . $status_def_ary[$status]['sql'], $sql_bind);

            $contacts = [];

            foreach ($c_ary as $c)
            {
                $contacts[$c['user_id']][$c['abbrev']][] = [
                    'value'         => $c['value'],
                    'access'        => $c['access'],
                ];
            }
        }

        if (isset($show_columns['d']) && !$su->is_master())
        {
            if (($pp->is_guest() && $su->schema())
                || !isset($contacts[$su->id()]['adr']))
            {
                $my_adr = $db->fetchColumn('select c.value
                    from ' . $su->schema() . '.contact c, ' .
                        $su->schema() . '.type_contact tc
                    where c.user_id = ?
                        and c.id_type_contact = tc.id
                        and tc.abbrev = \'adr\'', [$su->id()]);
            }
            else if (!$pp->is_guest()
                && isset($contacts[$su->id()]['adr'][0]['value']))
            {
                $my_adr = trim($contacts[$su->id()]['adr'][0]['value']);
            }

            if (isset($my_adr))
            {
                $ref_geo = $cache_service->get('geo_' . $my_adr);
            }
        }

        if (isset($show_columns['m']))
        {
            $msgs_count = [];

            if (isset($show_columns['m']['offers']))
            {
                $ary = $db->fetchAll('select count(m.id), m.user_id
                    from ' . $pp->schema() . '.messages m, ' .
                        $pp->schema() . '.users u
                    where m.is_offer = \'t\'
                        and m.user_id = u.id
                        and ' . $status_def_ary[$status]['sql'] . '
                    group by m.user_id', $sql_bind);

                foreach ($ary as $a)
                {
                    $msgs_count[$a['user_id']]['offers'] = $a['count'];
                }
            }

            if (isset($show_columns['m']['wants']))
            {
                $ary = $db->fetchAll('select count(m.id), m.user_id
                    from ' . $pp->schema() . '.messages m, ' .
                        $pp->schema() . '.users u
                    where m.is_want = \'t\'
                        and m.user_id = u.id
                        and ' . $status_def_ary[$status]['sql'] . '
                    group by m.user_id', $sql_bind);

                foreach ($ary as $a)
                {
                    $msgs_count[$a['user_id']]['wants'] = $a['count'];
                }
            }

            if (isset($show_columns['m']['total']))
            {
                $ary = $db->fetchAll('select count(m.id), m.user_id
                    from ' . $pp->schema() . '.messages m, ' .
                        $pp->schema() . '.users u
                    where m.user_id = u.id
                        and ' . $status_def_ary[$status]['sql'] . '
                    group by m.user_id', $sql_bind);

                foreach ($ary as $a)
                {
                    $msgs_count[$a['user_id']]['total'] = $a['count'];
                }
            }
        }

        if (isset($show_columns['a']))
        {
            $activity = [];

            $ts = gmdate('Y-m-d H:i:s', time() - ($activity_days * 86400));
            $sql_bind = [$ts];

            $activity_filter_code = trim($activity_filter_code);

            if ($activity_filter_code)
            {
                [$code_only_activity_filter_code] = explode(' ', $activity_filter_code);
                $and = ' and u.code <> ? ';
                $sql_bind[] = trim($code_only_activity_filter_code);
            }
            else
            {
                $and = ' and 1 = 1 ';
            }

            $trans_in_ary = $db->fetchAll('select sum(t.amount),
                    count(t.id), t.id_to
                from ' . $pp->schema() . '.transactions t, ' .
                    $pp->schema() . '.users u
                where t.id_from = u.id
                    and t.created_at > ?' . $and . '
                group by t.id_to', $sql_bind);

            $trans_out_ary = $db->fetchAll('select sum(t.amount),
                    count(t.id), t.id_from
                from ' . $pp->schema() . '.transactions t, ' .
                    $pp->schema() . '.users u
                where t.id_to = u.id
                    and t.created_at > ?' . $and . '
                group by t.id_from', $sql_bind);

            foreach ($trans_in_ary as $trans_in)
            {
                $activity[$trans_in['id_to']] ??= [
                    'trans'	    => ['total' => 0],
                    'amount'    => ['total' => 0],
                ];

                $activity[$trans_in['id_to']]['trans']['in'] = $trans_in['count'];
                $activity[$trans_in['id_to']]['amount']['in'] = $trans_in['sum'];
                $activity[$trans_in['id_to']]['trans']['total'] += $trans_in['count'];
                $activity[$trans_in['id_to']]['amount']['total'] += $trans_in['sum'];
            }

            foreach ($trans_out_ary as $trans_out)
            {
                $activity[$trans_out['id_from']] ??= [
                    'trans'	    => ['total' => 0],
                    'amount'    => ['total' => 0],
                ];

                $activity[$trans_out['id_from']]['trans']['out'] = $trans_out['count'];
                $activity[$trans_out['id_from']]['amount']['out'] = $trans_out['sum'];
                $activity[$trans_out['id_from']]['trans']['total'] += $trans_out['count'];
                $activity[$trans_out['id_from']]['amount']['total'] += $trans_out['sum'];
            }
        }

        if ($pp->is_admin())
        {
            $btn_nav_render->csv();

            $btn_top_render->add('users_add', $pp->ary(),
                [], 'Gebruiker toevoegen');

            $btn_top_render->local('#bulk_actions', 'Bulk acties', 'envelope-o');
        }

        $btn_nav_render->columns_show();

        self::btn_nav($btn_nav_render, $pp->ary(), $params, 'users_list');
        self::heading($heading_render);

        $assets_service->add([
            'calc_sum.js',
            'users_distance.js',
            'datepicker',
        ]);

        if ($pp->is_admin())
        {
            $assets_service->add([
                'codemirror',
                'summernote',
                'summernote_email.js',
                'table_sel.js',
            ]);
        }

        $f_col = '';

        $f_col .= '<div class="panel panel-info collapse" ';
        $f_col .= 'id="columns_show">';
        $f_col .= '<div class="panel-heading">';
        $f_col .= '<h2>Weergave kolommen</h2>';

        $f_col .= '<div class="row">';

        foreach ($columns as $group => $ary)
        {
            if ($group === 'p')
            {
                continue;
            }

            if ($group === 'm' || $group === 'c')
            {
                $f_col .= '</div>';
            }

            if ($group === 'u' || $group === 'c' || $group === 'm')
            {
                $f_col .= '<div class="col-md-4">';
            }

            if ($group === 'c')
            {
                $f_col .= '<h3>Contacten</h3>';
            }
            else if ($group === 'd')
            {
                $f_col .= '<h3>Afstand</h3>';
                $f_col .= '<p>Tussen eigen adres en adres van gebruiiker. ';
                $f_col .= 'De kolom wordt niet getoond wanneer het eigen adres ';
                $f_col .= 'niet ingesteld is.</p>';
            }
            else if ($group === 'a')
            {
                $f_col .= '<h3>Transacties/activiteit</h3>';

                $f_col .= '<div class="form-group">';
                $f_col .= '<label for="p_activity_days" ';
                $f_col .= 'class="control-label">';
                $f_col .= 'In periode';
                $f_col .= '</label>';
                $f_col .= '<div class="input-group">';
                $f_col .= '<span class="input-group-addon">';
                $f_col .= 'dagen';
                $f_col .= '</span>';
                $f_col .= '<input type="number" ';
                $f_col .= 'id="p_activity_days" ';
                $f_col .= 'name="sh[p][a][days]" ';
                $f_col .= 'value="';
                $f_col .= $activity_days;
                $f_col .= '" ';
                $f_col .= 'size="4" min="1" class="form-control">';
                $f_col .= '</div>';
                $f_col .= '</div>';

                $typeahead_service->ini($pp->ary())
                    ->add('accounts', ['status' => 'active']);

                if (!$pp->is_guest())
                {
                    $typeahead_service->add('accounts', ['status' => 'extern']);
                }

                if ($pp->is_admin())
                {
                    $typeahead_service->add('accounts', ['status' => 'inactive'])
                        ->add('accounts', ['status' => 'ip'])
                        ->add('accounts', ['status' => 'im']);
                }

                $f_col .= '<div class="form-group">';
                $f_col .= '<label for="p_activity_filter_code" ';
                $f_col .= 'class="control-label">';
                $f_col .= 'Exclusief tegenpartij';
                $f_col .= '</label>';
                $f_col .= '<div class="input-group">';
                $f_col .= '<span class="input-group-addon">';
                $f_col .= '<i class="fa fa-user"></i>';
                $f_col .= '</span>';
                $f_col .= '<input type="text" ';
                $f_col .= 'name="sh[p][a][code]" ';
                $f_col .= 'id="p_activity_filter_code" ';
                $f_col .= 'value="';
                $f_col .= $activity_filter_code;
                $f_col .= '" ';
                $f_col .= 'placeholder="Account Code" ';
                $f_col .= 'class="form-control" ';
                $f_col .= 'data-typeahead="';

                $f_col .= $typeahead_service->str([
                    'filter'		=> 'accounts',
                    'newuserdays'	=> $config_service->get('newuserdays', $pp->schema()),
                ]);

                $f_col .= '">';
                $f_col .= '</div>';
                $f_col .= '</div>';

                foreach ($ary as $a_type => $a_ary)
                {
                    foreach($a_ary as $key => $lbl)
                    {
                        $checkbox_id = 'id_' . $group . '_' . $a_type . '_' . $key;

                        $f_col .= '<div class="checkbox">';
                        $f_col .= '<label for="';
                        $f_col .= $checkbox_id;
                        $f_col .= '">';
                        $f_col .= '<input type="checkbox" ';
                        $f_col .= 'id="';
                        $f_col .= $checkbox_id;
                        $f_col .= '" ';
                        $f_col .= 'name="sh[' . $group . '][' . $a_type . '][' . $key . ']" ';
                        $f_col .= 'value="1"';
                        $f_col .= isset($show_columns[$group][$a_type][$key]) ? ' checked="checked"' : '';
                        $f_col .= '> ' . $lbl;
                        $f_col .= '</label>';
                        $f_col .= '</div>';
                    }
                }

                $f_col .= '</div>';

                continue;
            }
            else if ($group === 'm')
            {
                $f_col .= '<h3>Vraag en aanbod</h3>';
            }

            foreach ($ary as $key => $lbl)
            {
                $checkbox_id = 'id_' . $group . '_' . $key;

                $f_col .= '<div class="checkbox">';
                $f_col .= '<label for="';
                $f_col .= $checkbox_id;
                $f_col .= '">';
                $f_col .= '<input type="checkbox" name="sh[';
                $f_col .= $group . '][' . $key . ']" ';
                $f_col .= 'id="';
                $f_col .= $checkbox_id;
                $f_col .= '" ';
                $f_col .= 'value="1"';
                $f_col .= isset($show_columns[$group][$key]) ? ' checked="checked"' : '';
                $f_col .= '> ';
                $f_col .= $lbl;

                if ($key === 'adr')
                {
                    $f_col .= ', split door teken: ';
                    $f_col .= '<input type="text" ';
                    $f_col .= 'name="sh[p][c][adr_split]" ';
                    $f_col .= 'size="1" value="';
                    $f_col .= $adr_split;
                    $f_col .= '">';
                }

                if ($key === 'balance_date')
                {
                    $f_col .= '<div class="input-group">';
                    $f_col .= '<span class="input-group-addon">';
                    $f_col .= '<i class="fa fa-calendar"></i>';
                    $f_col .= '</span>';
                    $f_col .= '<input type="text" ';
                    $f_col .= 'class="form-control" ';
                    $f_col .= 'name="sh[p][u][balance_date]" ';
                    $f_col .= 'data-provide="datepicker" ';
                    $f_col .= 'data-date-format="';
                    $f_col .= $date_format_service->datepicker_format($pp->schema());
                    $f_col .= '" ';
                    $f_col .= 'data-date-language="nl" ';
                    $f_col .= 'data-date-today-highlight="true" ';
                    $f_col .= 'data-date-autoclose="true" ';
                    $f_col .= 'data-date-enable-on-readonly="false" ';
                    $f_col .= 'data-date-end-date="0d" ';
                    $f_col .= 'data-date-orientation="bottom" ';
                    $f_col .= 'placeholder="';
                    $f_col .= $date_format_service->datepicker_placeholder($pp->schema());
                    $f_col .= '" ';
                    $f_col .= 'value="';
                    $f_col .= $balance_date;
                    $f_col .= '">';
                    $f_col .= '</div>';

                    $columns['u']['balance_date'] = 'Saldo op ' . $balance_date;
                }

                $f_col .= '</label>';
                $f_col .= '</div>';
            }
        }

        $f_col .= '</div>';
        $f_col .= '<div class="row">';
        $f_col .= '<div class="col-md-12">';
        $f_col .= '<input type="submit" name="show" ';
        $f_col .= 'class="btn btn-default" ';
        $f_col .= 'value="Pas weergave kolommen aan">';
        $f_col .= '</div>';
        $f_col .= '</div>';
        $f_col .= '</div>';
        $f_col .= '</div>';

        $out = self::get_filter_and_tab_selector(
            $params,
            $f_col,
            $q,
            $link_render,
            $config_service,
            $pp,
            $vr
        );

        $out .= '<div class="panel panel-success printview">';
        $out .= '<div class="table-responsive">';

        $out .= '<table class="table table-bordered table-striped table-hover footable csv" ';
        $out .= 'data-filtering="true" data-filter-delay="0" ';
        $out .= 'data-filter="#q" data-filter-min="1" data-cascade="true" ';
        $out .= 'data-empty="Er zijn geen gebruikers ';
        $out .= 'volgens de selectiecriteria" ';
        $out .= 'data-sorting="true" ';
        $out .= 'data-filter-placeholder="Zoeken" ';
        $out .= 'data-filter-position="left"';

        if (count($ref_geo))
        {
            $out .= ' data-lat="' . $ref_geo['lat'] . '" ';
            $out .= 'data-lng="' . $ref_geo['lng'] . '"';
        }

        $out .= '>';
        $out .= '<thead>';

        $out .= '<tr>';

        $numeric_keys = [
            'balance'			=> true,
            'balance_date'	=> true,
        ];

        $date_keys = [
            'created_at'    => true,
            'last_edit_at'	=> true,
            'adate'			=> true,
            'last_login'	=> true,
        ];

        $link_user_keys = [
            'code'		=> true,
            'name'		=> true,
        ];

        foreach ($show_columns as $group => $ary)
        {
            if ($group === 'p')
            {
                continue;
            }
            else if ($group === 'a')
            {
                foreach ($ary as $a_key => $a_ary)
                {
                    foreach ($a_ary as $key => $one)
                    {
                        $out .= '<th data-type="numeric">';
                        $out .= $columns[$group][$a_key][$key];
                        $out .= '</th>';
                    }
                }

                continue;
            }
            else if ($group === 'd')
            {
                if (count($ref_geo))
                {
                    foreach($ary as $key => $one)
                    {
                        $out .= '<th>';
                        $out .= $columns[$group][$key];
                        $out .= '</th>';
                    }
                }

                continue;
            }
            else if ($group === 'c')
            {
                $tpl = '<th data-hide="tablet, phone" data-sort-ignore="true">%1$s</th>';

                foreach ($ary as $key => $one)
                {
                    if ($key == 'adr' && $adr_split != '')
                    {
                        $out .= sprintf($tpl, 'Adres (1)');
                        $out .= sprintf($tpl, 'Adres (2)');
                        continue;
                    }

                    $out .= sprintf($tpl, $columns[$group][$key]);
                }

                continue;
            }
            else if ($group === 'u')
            {
                foreach ($ary as $key => $one)
                {
                    $data_type =  isset($numeric_keys[$key]) ? ' data-type="numeric"' : '';
                    $data_sort_initial = $key === 'code' ? ' data-sort-initial="true"' : '';

                    $out .= '<th' . $data_type . $data_sort_initial . '>';
                    $out .= $columns[$group][$key];
                    $out .= '</th>';
                }

                continue;
            }
            else if ($group === 'm')
            {
                foreach ($ary as $key => $one)
                {
                    $out .= '<th data-type="numeric">';
                    $out .= $columns[$group][$key];
                    $out .= '</th>';
                }

                continue;
            }
        }

        $out .= '</tr>';

        $out .= '</thead>';
        $out .= '<tbody>';

        $can_link = $pp->is_admin();

        foreach($users as $u)
        {
            if (($pp->is_user() || $pp->is_guest())
                && ($u['status'] === 1 || $u['status'] === 2))
            {
                $can_link = true;
            }

            $id = $u['id'];

            $row_stat = $u['status'];

            if (isset($u['adate'])
                && $u['status'] === 1
                && $config_service->get_new_user_treshold($pp->schema()) < strtotime($u['adate']))
            {
                $row_stat = 3;
            }

            $first = true;

            $out .= '<tr';

            if (isset(StatusCnst::CLASS_ARY[$row_stat]))
            {
                $out .= ' class="';
                $out .= StatusCnst::CLASS_ARY[$row_stat];
                $out .= '"';
            }

            $out .= ' data-balance="';
            $out .= $u['balance'];
            $out .= '">';

            if (isset($show_columns['u']))
            {
                foreach ($show_columns['u'] as $key => $one)
                {
                    $out .= '<td';
                    $out .= isset($date_keys[$key]) ? ' data-value="' . $u[$key] . '"' : '';
                    $out .= '>';

                    $td = '';

                    if (isset($link_user_keys[$key]))
                    {
                        if ($can_link)
                        {
                            $td .= $link_render->link_no_attr($vr->get('users_show'), $pp->ary(),
                                ['id' => $u['id'], 'status' => $status], $u[$key] ?: '**leeg**');
                        }
                        else
                        {
                            $td .= htmlspecialchars($u[$key], ENT_QUOTES);
                        }
                    }
                    else if (isset($date_keys[$key]))
                    {
                        if ($u[$key])
                        {
                            $td .= $date_format_service->get($u[$key], 'day', $pp->schema());
                        }
                        else
                        {
                            $td .= '&nbsp;';
                        }
                    }
                    else if ($key === 'fullname')
                    {
                        if ($item_access_service->is_visible($u['fullname_access']))
                        {
                            if ($can_link)
                            {
                                $td .= $link_render->link_no_attr($vr->get('users_show'), $pp->ary(),
                                    ['id' => $u['id'], 'status' => $status], $u['fullname']);
                            }
                            else
                            {
                                $td .= htmlspecialchars($u['fullname'], ENT_QUOTES);
                            }
                        }
                        else
                        {
                            $td .= '<span class="btn btn-default">';
                            $td .= 'verborgen</span>';
                        }
                    }
                    else if ($key === 'role')
                    {
                        $td .= RoleCnst::LABEL_ARY[$u['role']];
                    }
                    else
                    {
                        $td .= htmlspecialchars((string) $u[$key]);
                    }

                    if ($pp->is_admin() && $first)
                    {
                        $out .= strtr(BulkCnst::TPL_CHECKBOX_ITEM, [
                            '%id%'      => $id,
                            '%attr%'    => isset($selected_users[$id]) ? ' checked' : '',
                            '%label%'   => $td,
                        ]);

                        $first = false;
                    }
                    else
                    {
                        $out .= $td;
                    }

                    $out .= '</td>';
                }
            }

            if (isset($show_columns['c']))
            {
                foreach ($show_columns['c'] as $key => $one)
                {
                    $out .= '<td>';

                    if ($key === 'adr' && $adr_split !== '')
                    {
                        if (!isset($contacts[$id][$key]))
                        {
                            $out .= '&nbsp;</td><td>&nbsp;</td>';
                            continue;
                        }

                        [$adr_1, $adr_2] = explode(trim($adr_split), $contacts[$id]['adr'][0]['value']);

                        $out .= self::get_contacts_str($item_access_service, [[
                            'value'     => $adr_1,
                            'access'    => $contacts[$id]['adr'][0]['access']]],
                        'adr');

                        $out .= '</td><td>';

                        $out .= self::get_contacts_str($item_access_service, [[
                            'value'    => $adr_2,
                            'access'   => $contacts[$id]['adr'][0]['access']]],
                        'adr');
                    }
                    else if (isset($contacts[$id][$key]))
                    {
                        $out .= self::get_contacts_str($item_access_service, $contacts[$id][$key], $key);
                    }
                    else
                    {
                        $out .= '&nbsp;';
                    }

                    $out .= '</td>';
                }
            }

            if (isset($show_columns['d']) && count($ref_geo))
            {
                $out .= '<td data-value="5000000"';

                $adr_ary = $contacts[$id]['adr'][0] ?? [];

                if (isset($adr_ary['access']))
                {
                    if ($item_access_service->is_visible($adr_ary['access']))
                    {
                        if (count($adr_ary) && $adr_ary['value'])
                        {
                            $geo = $cache_service->get('geo_' . $adr_ary['value']);

                            if ($geo)
                            {
                                $out .= ' data-lat="';
                                $out .= $geo['lat'];
                                $out .= '" data-lng="';
                                $out .= $geo['lng'];
                                $out .= '"';
                            }
                        }

                        $out .= '><i class="fa fa-times"></i>';
                    }
                    else
                    {
                        $out .= '><span class="btn btn-default">verborgen</span>';
                    }
                }
                else
                {
                    $out .= '><i class="fa fa-times"></i>';
                }

                $out .= '</td>';
            }

            if (isset($show_columns['m']))
            {
                foreach($show_columns['m'] as $key => $one)
                {
                    $out .= '<td>';

                    if (isset($msgs_count[$id][$key]))
                    {
                        $out .= $link_render->link_no_attr($vr->get('messages'), $pp->ary(),
                            [
                                'f'	=> [
                                    'uid' 	=> $id,
                                    'type' 	=> $message_type_filter[$key],
                                ],
                            ],
                            (string) $msgs_count[$id][$key]);
                    }

                    $out .= '</td>';
                }
            }

            if (isset($show_columns['a']))
            {
                $from_date = $date_format_service->get_from_unix(time() - ($activity_days * 86400), 'day', $pp->schema());

                foreach($show_columns['a'] as $a_key => $a_ary)
                {
                    foreach ($a_ary as $key => $one)
                    {
                        $out .= '<td>';

                        if (isset($activity[$id][$a_key][$key]))
                        {
                            if (isset($code_only_activity_filter_code))
                            {
                                $out .= $activity[$id][$a_key][$key];
                            }
                            else
                            {
                                $out .= $link_render->link_no_attr('transactions', $pp->ary(),
                                    [
                                        'f' => [
                                            'fcode'	=> $key === 'in' ? '' : $u['code'],
                                            'tcode'	=> $key === 'out' ? '' : $u['code'],
                                            'andor'	=> $key === 'total' ? 'or' : 'and',
                                            'fdate' => $from_date,
                                        ],
                                    ],
                                    (string) $activity[$id][$a_key][$key]);
                            }
                        }

                        $out .= '</td>';
                    }
                }
            }

            $out .= '</tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';
        $out .= '</div></div>';

        $out .= '<div class="row"><div class="col-md-12">';
        $out .= '<p><span class="pull-right">Totaal saldo: <span id="sum"></span> ';
        $out .= $config_service->get('currency', $pp->schema());
        $out .= '</span></p>';
        $out .= '</div></div>';

        if ($pp->is_admin() & isset($show_columns['u']))
        {
            $out .= BulkCnst::TPL_SELECT_BUTTONS;

            $out .= '<h3>Bulk acties met geselecteerde gebruikers</h3>';
            $out .= '<div class="panel panel-info">';
            $out .= '<div class="panel-heading">';

            $out .= '<ul class="nav nav-tabs" role="tablist">';

            $out .= '<li class="active">';
            $out .= '<a href="#mail_tab" data-toggle="tab">Mail</a></li>';
            $out .= '<li class="dropdown">';

            $out .= '<a class="dropdown-toggle" data-toggle="dropdown" href="#">Veld aanpassen';
            $out .= '<span class="caret"></span></a>';
            $out .= '<ul class="dropdown-menu">';

            foreach (BulkCnst::USER_TABS as $k => $t)
            {
                $out .= '<li>';
                $out .= '<a href="#' . $k . '_tab" data-toggle="tab">';
                $out .= $t['lbl'];
                $out .= '</a></li>';
            }

            $out .= '</ul>';
            $out .= '</li>';
            $out .= '</ul>';

            $out .= '<div class="tab-content">';

            $out .= '<div role="tabpanel" class="tab-pane active" id="mail_tab">';
            $out .= '<h3>E-Mail verzenden naar geselecteerde gebruikers</h3>';

            $out .= '<form method="post">';

            $out .= '<div class="form-group">';
            $out .= '<input type="text" class="form-control" id="bulk_mail_subject" name="bulk_mail_subject" ';
            $out .= 'placeholder="Onderwerp" ';
            $out .= 'value="';
            $out .= $bulk_mail_subject;
            $out .= '" required>';
            $out .= '</div>';

            $out .= '<div class="form-group">';
            $out .= '<textarea name="bulk_mail_content" ';
            $out .= 'class="form-control summernote" ';
            $out .= 'id="bulk_mail_content" rows="8" ';
            $out .= 'data-template-vars="';
            $out .= implode(',', array_keys(BulkCnst::USER_TPL_VARS));
            $out .= '" ';
            $out .= 'required>';
            $out .= $bulk_mail_content;
            $out .= '</textarea>';
            $out .= '</div>';

            $out .= strtr(BulkCnst::TPL_CHECKBOX, [
                '%name%'    => 'bulk_mail_cc',
                '%label%'   => 'Stuur een kopie met verzendinfo naar mijzelf',
                '%attr%'    => $bulk_mail_cc ? ' checked' : '',
            ]);

            $out .= strtr(BulkCnst::TPL_CHECKBOX, [
                '%name%'    => 'bulk_verify[mail]',
                '%label%'   => 'Ik heb mijn bericht nagelezen en nagekeken dat de juiste gebruikers geselecteerd zijn.',
                '%attr%'    => ' required',
            ]);

            $out .= '<input type="submit" value="Zend test E-mail naar mijzelf" ';
            $out .= 'name="bulk_submit[mail_test]" class="btn btn-info btn-lg">&nbsp;';
            $out .= '<input type="submit" value="Verzend" name="bulk_submit[mail]" ';
            $out .= 'class="btn btn-info btn-lg">';

            $out .= $form_token_service->get_hidden_input();
            $out .= '</form>';
            $out .= '</div>';

            foreach(BulkCnst::USER_TABS as $k => $t)
            {
                $out .= '<div role="tabpanel" class="tab-pane" id="';
                $out .= $k;
                $out .= '_tab"';
                $out .= '>';
                $out .= '<h3>Veld aanpassen: ' . $t['lbl'] . '</h3>';

                $out .= '<form method="post">';

                $bulk_field_name = 'bulk_field[' . $k . ']';

                if (isset($t['item_access']))
                {
                    $out .= $item_access_service->get_radio_buttons($bulk_field_name);
                }
                else
                {
                    $options = '';

                    if (isset($t['options']))
                    {
                        $tpl = BulkCnst::TPL_SELECT;
                        $options = $select_render->get_options($t['options'], '');
                    }
                    else if (isset($t['type'])
                        && $t['type'] === 'checkbox')
                    {
                        $tpl = BulkCnst::TPL_CHECKBOX;
                    }
                    else
                    {
                        $tpl = BulkCnst::TPL_INPUT;
                    }

                    $out .= strtr($tpl, [
                        '%name%'        => $bulk_field_name,
                        '%label%'       => $t['lbl'],
                        '%type%'        => $t['type'] ?? '',
                        '%options%'     => $options,
                        '%required%'    => isset($t['required']) ? ' required' : '',
                        '%fa%'          => $t['fa'] ?? '',
                        '%attr%'        => $t['attr'] ?? '',
                        '%explain%'     => $t['explain'] ?? '',
                    ]);
                }

                $out .= strtr(BulkCnst::TPL_CHECKBOX, [
                    '%name%'    => 'bulk_verify[' . $k  . ']',
                    '%label%'   => 'Ik heb de ingevulde waarde nagekeken en dat de juiste gebruikers geselecteerd zijn.',
                    '%attr%'    => ' required',
                ]);

                $out .= '<input type="submit" value="Veld aanpassen" ';
                $out .= 'name="bulk_submit[' . $k . ']" class="btn btn-primary btn-lg">';
                $out .= $form_token_service->get_hidden_input();
                $out .= '</form>';

                $out .= '</div>';
            }

            $out .= '<div class="clearfix"></div>';
            $out .= '</div>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $menu_service->set('users');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }

    static public function btn_nav(
        BtnNavRender $btn_nav_render,
        array $pp_ary,
        array $params,
        string $matched_route
    ):void
    {
        $admin_suffix = $pp_ary['role_short'] === 'a' ? '_admin' : '';

        $btn_nav_render->view('users_list' . $admin_suffix, $pp_ary,
            $params, 'Lijst', 'align-justify',
            $matched_route === 'users_list');

        $btn_nav_render->view('users_tiles' . $admin_suffix, $pp_ary,
            $params, 'Tegels met foto\'s', 'th',
            $matched_route === 'users_tiles');

        unset($params['status']);

        $btn_nav_render->view('users_map', $pp_ary,
            $params, 'Kaart', 'map-marker',
            $matched_route === 'users_map');
    }

    static public function heading(HeadingRender $heading_render):void
    {
        $heading_render->add('Leden');
        $heading_render->fa('users');
    }

    static public function get_status_def_ary(
        ConfigService $config_service,
        PageParamsService $pp
    ):array
    {
        $new_user_treshold = $config_service->get_new_user_treshold($pp->schema());

        $status_def_ary = [
            'active'	=> [
                'lbl'	=> $pp->is_admin() ? 'Actief' : 'Alle',
                'sql'	=> 'u.status in (1, 2)',
                'st'	=> [1, 2],
            ],
            'new'		=> [
                'lbl'	=> 'Instappers',
                'sql'	=> 'u.status = 1 and u.adate > ?',
                'sql_bind'	=> gmdate('Y-m-d H:i:s', $new_user_treshold),
                'cl'	=> 'success',
                'st'	=> 3,
            ],
            'leaving'	=> [
                'lbl'	=> 'Uitstappers',
                'sql'	=> 'u.status = 2',
                'cl'	=> 'danger',
                'st'	=> 2,
            ],
        ];

        if ($pp->is_admin())
        {
            $status_def_ary = $status_def_ary + [
                'inactive'	=> [
                    'lbl'	=> 'Inactief',
                    'sql'	=> 'u.status = 0',
                    'cl'	=> 'inactive',
                    'st'	=> 0,
                ],
                'ip'		=> [
                    'lbl'	=> 'Info-pakket',
                    'sql'	=> 'u.status = 5',
                    'cl'	=> 'warning',
                    'st'	=> 5,
                ],
                'im'		=> [
                    'lbl'	=> 'Info-moment',
                    'sql'	=> 'u.status = 6',
                    'cl'	=> 'info',
                    'st'	=> 6
                ],
                'extern'	=> [
                    'lbl'	=> 'Extern',
                    'sql'	=> 'u.status = 7',
                    'cl'	=> 'extern',
                    'st'	=> 7,
                ],
                'all'		=> [
                    'lbl'	=> 'Alle',
                    'sql'	=> '1 = 1',
                ],
            ];
        }

        return $status_def_ary;
    }

    static public function get_filter_and_tab_selector(
        array $params,
        string $before,
        string $q,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp,
        VarRouteService $vr
    ):string
    {
        $out = '';

        $out .= '<form method="get">';

        foreach ($params as $k => $v)
        {
            $out .= '<input type="hidden" name="' . $k . '" value="' . $v . '">';
        }

        if ($pp->is_guest() && $pp->org_system())
        {
            $out .= '<input type="hidden" name="os" value="' . $pp->org_system() . '">';
        }

        $out .= $before;

        $out .= '<br>';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<div class="row">';
        $out .= '<div class="col-xs-12">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-search"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="q" name="q" value="' . $q . '" ';
        $out .= 'placeholder="Zoeken">';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '</form>';

        $out .= '<div class="pull-right hidden-xs hidden-sm print-hide">';
        $out .= 'Totaal: <span id="total"></span>';
        $out .= '</div>';

        $out .= '<ul class="nav nav-tabs" id="nav-tabs">';

        $nav_params = $params;

        foreach (self::get_status_def_ary($config_service, $pp) as $k => $tab)
        {
            $nav_params['status'] = $k;

            $out .= '<li';
            $out .= $params['status'] === $k ? ' class="active"' : '';
            $out .= '>';

            $class_ary = isset($tab['cl']) ? ['class' => 'bg-' . $tab['cl']] : [];

            $out .= $link_render->link(
                $vr->get('users'),
                $pp->ary(),
                $nav_params,
                $tab['lbl'],
                $class_ary
            );

            $out .= '</li>';
        }

        $out .= '</ul>';

        return $out;
    }

    public static function get_contacts_str(
        ItemAccessService $item_access_service,
        array $contacts,
        string $abbrev
    ):string
    {
        $ret = '';

        if (count($contacts))
        {
            end($contacts);
            $end = key($contacts);

            $tpl = '%1$s';

            if ($abbrev === 'mail')
            {
                $tpl = '<a href="mailto:%1$s">%1$s</a>';
            }
            else if ($abbrev === 'web')
            {
                $tpl = '<a href="%1$s">%1$s</a>';
            }

            foreach ($contacts as $key => $contact)
            {
                if ($item_access_service->is_visible($contact['access']))
                {
                    $ret .= sprintf($tpl, htmlspecialchars($contact['value'], ENT_QUOTES));

                    if ($key === $end)
                    {
                        break;
                    }

                    $ret .= ',<br>';

                    continue;
                }

                $ret .= '<span class="btn btn-default">';
                $ret .= 'verborgen</span>';
                $ret .= '<br>';
            }
        }
        else
        {
            $ret .= '&nbsp;';
        }

        return $ret;
    }

    public static function array_intersect_key_recursive(array $ary_1, array $ary_2)
    {
        $ary_1 = array_intersect_key($ary_1, $ary_2);

        foreach ($ary_1 as $key => &$val)
        {
            if (is_array($val))
            {
                $val = is_array($ary_2[$key]) ? self::array_intersect_key_recursive($val, $ary_2[$key]) : $val;
            }
        }

        return $ary_1;
    }
}
