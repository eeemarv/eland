<?php declare(strict_types=1);

namespace App\Controller\Users;

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
use App\Repository\AccountRepository;
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
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class UsersListController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{status}',
        name: 'users_list',
        methods: ['GET', 'POST'],
        priority: 20,
        requirements: [
            'status'        => '%assert.account_status%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
        ],
        defaults: [
            'status'        => 'active',
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        string $status,
        Db $db,
        AccountRepository $account_repository,
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
        if (!$pp->is_admin() && !in_array($status, ['active', 'new', 'leaving']))
        {
            throw new AccessDeniedHttpException('No access for status: ' . $status);
        }

        $full_name_enabled = $config_service->get_bool('users.fields.full_name.enabled', $pp->schema());
        $postcode_enabled = $config_service->get_bool('users.fields.postcode.enabled', $pp->schema());
        $birthday_enabled = $config_service->get_bool('users.fields.birthday.enabled', $pp->schema());
        $hobbies_enabled = $config_service->get_bool('users.fields.hobbies.enabled', $pp->schema());
        $comments_enabled = $config_service->get_bool('users.fields.comments.enabled', $pp->schema());
        $admin_comments_enabled = $config_service->get_bool('users.fields.admin_comments.enabled', $pp->schema());
        $periodic_mail_enabled = $config_service->get_bool('periodic_mail.enabled', $pp->schema());

        $mollie_enabled = $config_service->get_bool('mollie.enabled', $pp->schema());
        $messages_enabled = $config_service->get_bool('messages.enabled', $pp->schema());
        $transactions_enabled = $config_service->get_bool('transactions.enabled', $pp->schema());

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

        $new_user_treshold = $config_service->get_new_user_treshold($pp->schema());

        $user_tabs = BulkCnst::USER_TABS;

        if (!$full_name_enabled)
        {
            unset($user_tabs['full_name_access']);
        }

        if (!$comments_enabled)
        {
            unset($user_tabs['comments']);
        }

        if (!$admin_comments_enabled)
        {
            unset($user_tabs['admin_comments']);
        }

        if (!$transactions_enabled)
        {
            unset($user_tabs['min_limit'], $user_tabs['max_limit']);
        }

        if (!$periodic_mail_enabled)
        {
            unset($user_tabs['periodic_overview_en']);
        }

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
            else if (str_ends_with($bulk_submit_action, '_access'))
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

            $user_tab_data = $user_tabs[$bulk_submit_action] ?? [];

            if (!count($errors)
                && $bulk_submit_action === 'periodic_overview_en')
            {
                $db->executeStatement('update ' . $pp->schema() . '.users
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

                $intersystems_service->clear_cache();

                $alert_service->success('Het veld werd aangepast.');

                $redirect = true;
            }
            else if (!count($errors)
                && $transactions_enabled
                && $user_tab_data
                && in_array($bulk_submit_action, ['min_limit', 'max_limit']))
            {
                $store_value = $bulk_field_value === '' ? null : (int) $bulk_field_value;

                if ($bulk_submit_action === 'min_limit')
                {
                    foreach($user_ids as $user_id)
                    {
                        $account_repository->update_min_limit($user_id, $store_value, $su->id(), $pp->schema());
                    }
                    $alert_msg = 'De minimum limiet werd ';
                }
                else
                {
                    foreach($user_ids as $user_id)
                    {
                        $account_repository->update_max_limit($user_id, $store_value, $su->id(), $pp->schema());
                    }
                    $alert_msg = 'De maximum limiet werd ';
                }

                $logger->info('bulk: Set ' . $bulk_submit_action .
                    ' to ' . ($store_value ?? 'null') .
                    ' for users ' . $users_log,
                    ['schema' => $pp->schema()]);

                $alert_msg .=  isset($store_value) ? 'aangepast.' : 'gewist.';
                $alert_service->success($alert_msg);

                $redirect = true;
            }
            else if (!count($errors)
                && $user_tab_data)
            {
                $store_value = $bulk_field_value;

                $field_type = isset($user_tab_data['string']) ? \PDO::PARAM_STR : \PDO::PARAM_INT;

                $db->executeStatement('update ' . $pp->schema() . '.users
                    set ' . $bulk_submit_action . ' = ? where id in (?)',
                    [$store_value, $user_ids],
                    [$field_type, Db::PARAM_INT_ARRAY]);

                foreach ($user_ids as $user_id)
                {
                    $user_cache_service->clear($user_id, $pp->schema());
                }

                if ($bulk_field == 'status')
                {
                    $thumbprint_accounts_service->delete($pp->ary(), $pp->schema());
                }

                $logger->info('bulk: Set ' . $bulk_submit_action .
                    ' to ' . $store_value .
                    ' for users ' . $users_log,
                    ['schema' => $pp->schema()]);

                $intersystems_service->clear_cache();

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
                        'template'			=> 'skeleton/user',
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
                        'to_users'  => $sent_to_ary,
                        'user_id'   => $su->id(),
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
                        'template'			=> 'skeleton/admin_copy',
                        'pre_html_template'	=> $bulk_mail_content,
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

        /**
         * Fetch columns list
         */

        $sql_map = [
            'where'     => [],
            'where_or'  => [],
            'params'    => [],
            'types'     => [],
        ];

        $sql = [];
        $sql['common'] = $sql_map;
        $sql['common']['where'][] = '1 = 1';

        $status_def_ary = self::get_status_def_ary($config_service, $pp);

        $sql['status'] = $sql_map;

        foreach ($status_def_ary[$status]['sql'] as $st_def_key => $def_sql_ary)
        {
            foreach ($def_sql_ary as $def_val)
            {
                $sql['status'][$st_def_key][] = $def_val;
            }
        }

        $params = ['status'	=> $status];

        $ref_geo = [];

        $type_contact = $db->fetchAllAssociative('select id, abbrev, name
            from ' . $pp->schema() . '.type_contact', [], []);

        $columns = [
            'u'		=> [
                'code'		    => 'Code',
                'name'			=> 'Naam',
                'full_name'		=> 'Volledige naam',
                'postcode'		=> 'Postcode',
                'role'	        => 'Rol',
                'balance'		=> 'Saldo',
                'balance_date'	=> 'Saldo op ',
                'min'		    => 'Min',
                'max'		    => 'Max',
                'comments'		=> 'Commentaar',
                'hobbies'		=> 'Hobbies/interesses',
            ],
        ];

        if ($pp->is_admin())
        {
            $columns['u'] += [
                'birthday'              => 'Geboortedatum',
                'admin_comments'	    => 'Admin commentaar',
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

        if ($pp->is_admin())
        {
            $columns['mollie'] = [
                'mollie'    => 'Mollie',
            ];
        }

        $columns['m'] = [
            'wants'		=> 'Vraag',
            'offers'	=> 'Aanbod',
            'total'		=> 'Vraag en aanbod',
        ];

        $message_type_filter = [
            'wants'		=> ['want' => 'on'],
            'offers'	=> ['offer' => 'on'],
            'total'		=> [],
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

        if (!$full_name_enabled)
        {
            unset($columns['u']['full_name']);
        }

        if (!$postcode_enabled)
        {
            unset($columns['u']['postcode']);
        }

        if (!$comments_enabled)
        {
            unset($columns['u']['comments']);
        }

        if (!$hobbies_enabled)
        {
            unset($columns['u']['hobbies']);
        }

        if (!$birthday_enabled)
        {
            unset($columns['u']['birthday']);
        }

        if (!$admin_comments_enabled)
        {
            unset($columns['u']['admin_comments']);
        }

        if (!$periodic_mail_enabled)
        {
            unset($columns['u']['periodic_overview_en']);
        }

        if (!$mollie_enabled)
        {
            unset($columns['mollie']);
        }

        if (!$transactions_enabled)
        {
            unset($columns['u']['balance']);
            unset($columns['u']['min']);
            unset($columns['u']['max']);
            unset($columns['u']['balance_date']);
            unset($columns['a']);
        }

        if (!$messages_enabled)
        {
            unset($columns['m']);
        }

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

        if (!$full_name_enabled)
        {
            unset($show_columns['u']['full_name']);
        }

        if (!$postcode_enabled)
        {
            unset($show_columns['u']['postcode']);
        }

        if (!$comments_enabled)
        {
            unset($show_columns['u']['comments']);
        }

        if (!$hobbies_enabled)
        {
            unset($show_columns['u']['hobbies']);
        }

        if (!$birthday_enabled)
        {
            unset($show_columns['u']['birthday']);
        }

        if (!$admin_comments_enabled)
        {
            unset($show_columns['u']['admin_comments']);
        }

        if (!$periodic_mail_enabled)
        {
            unset($show_columns['u']['periodic_overview_en']);
        }

        if (!$mollie_enabled)
        {
            unset($show_columns['mollie']);
        }

        if (!$transactions_enabled)
        {
            unset($show_columns['u']['balance']);
            unset($show_columns['u']['min']);
            unset($show_columns['u']['max']);
            unset($show_columns['u']['balance_date']);
            unset($show_columns['a']);
        }

        if (!$messages_enabled)
        {
            unset($show_columns['m']);
        }

        $adr_split = $show_columns['p']['c']['adr_split'] ?? '';
        $activity_days = $show_columns['p']['a']['days'] ?? 365;
        $activity_days = $activity_days < 1 ? 365 : $activity_days;
        $activity_filter_code = $show_columns['p']['a']['code'] ?? '';
        $balance_date = $show_columns['p']['u']['balance_date'] ?? '';
        $balance_date = trim($balance_date);

        $users = [];

        $sql_where = implode(' and ', array_merge(...array_column($sql, 'where')));
        $sql_params = array_merge(...array_column($sql, 'params'));
        $sql_types = array_merge(...array_column($sql, 'types'));

        $query = 'select u.*
            from ' . $pp->schema() . '.users u
            where ' . $sql_where . '
            order by u.code asc';

        $stmt = $db->executeQuery($query, $sql_params, $sql_types);

        while($row = $stmt->fetch())
        {
            $users[$row['id']] = $row;
        }

        if (isset($show_columns['u']['balance_date']))
        {
            if ($balance_date)
            {
                $balance_date_rev = $date_format_service->reverse($balance_date, $pp->schema());
            }

            if (!isset($balance_date_rev) || $balance_date_rev === '')
            {
                $balance_date = $date_format_service->get('now', 'day', $pp->schema());
                $balance_date_rev = 'now';
            }

            $datetime = new \DateTimeImmutable($balance_date_rev, new \DateTimeZone('UTC'));

            $balance_ary_on_date = $account_repository->get_balance_ary_on_date($datetime, $pp->schema());

            array_walk($users, function(&$user, $user_id) use ($balance_ary_on_date){
                $user['balance_date'] = $balance_ary_on_date[$user_id] ?? 0;
            });
        }

        $balance_ary = $account_repository->get_balance_ary($pp->schema());

        array_walk($users, function(&$user, $user_id) use ($balance_ary){
            $user['balance'] = $balance_ary[$user_id] ?? 0;
        });

        if (isset($show_columns['u']['min']))
        {
            $min_limit_ary = $account_repository->get_min_limit_ary($pp->schema());
            $min_intersect_ary = array_intersect_key($min_limit_ary, $users);

            foreach ($min_intersect_ary as $user_id => $min_limit)
            {
                $users[$user_id]['min'] = $min_limit;
            }
        }

        if (isset($show_columns['u']['max']))
        {
            $max_limit_ary = $account_repository->get_max_limit_ary($pp->schema());
            $max_intersect_ary = array_intersect_key($max_limit_ary, $users);

            foreach ($max_intersect_ary as $user_id => $max_limit)
            {
                $users[$user_id]['max'] = $max_limit;
            }
        }

        if (isset($show_columns['u']['last_login']))
        {
            $stmt = $db->executeQuery('select user_id, max(created_at) as last_login
                from ' . $pp->schema() . '.login
                group by user_id');

            while ($row = $stmt->fetch())
            {
                if (!isset($users[$row['user_id']]))
                {
                    continue;
                }

                $users[$row['user_id']]['last_login'] = $row['last_login'];
            }
        }

        if (isset($show_columns['c']) || (isset($show_columns['d']) && !$su->is_master()))
        {
            $contacts_query = 'select tc.abbrev,
                    c.user_id, c.value, c.access
                from ' . $pp->schema() . '.contact c, ' .
                    $pp->schema() . '.type_contact tc, ' .
                    $pp->schema() . '.users u
                where tc.id = c.id_type_contact ' .
                    (isset($show_columns['c']) ? '' : 'and tc.abbrev = \'adr\' ') .
                    'and c.user_id = u.id
                    and ' . $sql_where;

            $stmt = $db->executeQuery($contacts_query, $sql_params, $sql_types);

            $contacts = [];

            while ($row = $stmt->fetch())
            {
                $contacts[$row['user_id']][$row['abbrev']][] = [
                    'value'         => $row['value'],
                    'access'        => $row['access'],
                ];
            }
        }

        if (isset($show_columns['d']) && !$su->is_master())
        {
            if (($pp->is_guest() && $su->schema())
                || !isset($contacts[$su->id()]['adr']))
            {
                $my_adr = $db->fetchOne('select c.value
                    from ' . $su->schema() . '.contact c, ' .
                        $su->schema() . '.type_contact tc
                    where c.user_id = ?
                        and c.id_type_contact = tc.id
                        and tc.abbrev = \'adr\'',
                        [$su->id()], [\PDO::PARAM_INT]);
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

        if (isset($show_columns['mollie']) && $pp->is_admin())
        {
            $mollie_ary = [];

            $stmt = $db->executeQuery('select distinct on (u.id)
                u.id, p.is_paid, p.is_canceled,
                p.created_at, p.amount, r.description
                from ' . $pp->schema() . '.users u
                inner join ' . $pp->schema() . '.mollie_payments p
                    on u.id = p.user_id
                inner join ' . $pp->schema() . '.mollie_payment_requests r
                    on r.id = p.request_id
                where ' . $sql_where . '
                order by u.id asc, p.created_at desc',
                $sql_params, $sql_types);

            while (($row = $stmt->fetch()) !== false)
            {
                $mollie_ary[$row['id']] = $row;
            }
        }

        if (isset($show_columns['m']))
        {
            $msgs_count = [];

            if (isset($show_columns['m']['offers']))
            {
                $stmt = $db->executeQuery('select count(m.id), m.user_id
                    from ' . $pp->schema() . '.messages m, ' .
                        $pp->schema() . '.users u
                    where m.offer_want = \'offer\'
                        and m.user_id = u.id
                        and ' . $sql_where . '
                    group by m.user_id', $sql_params, $sql_types);

                while ($row = $stmt->fetch())
                {
                    $msgs_count[$row['user_id']]['offers'] = $row['count'];
                }
            }

            if (isset($show_columns['m']['wants']))
            {
                $stmt = $db->executeQuery('select count(m.id), m.user_id
                    from ' . $pp->schema() . '.messages m, ' .
                        $pp->schema() . '.users u
                    where m.offer_want = \'want\'
                        and m.user_id = u.id
                        and ' . $sql_where . '
                    group by m.user_id', $sql_params, $sql_types);

                while ($row = $stmt->fetch())
                {
                    $msgs_count[$row['user_id']]['wants'] = $row['count'];
                }
            }

            if (isset($show_columns['m']['total']))
            {
                $stmt = $db->executeQuery('select count(m.id), m.user_id
                    from ' . $pp->schema() . '.messages m, ' .
                        $pp->schema() . '.users u
                    where m.user_id = u.id
                        and ' . $sql_where . '
                    group by m.user_id', $sql_params, $sql_types);

                while ($row = $stmt->fetch())
                {
                    $msgs_count[$row['user_id']]['total'] = $row['count'];
                }
            }
        }

        if (isset($show_columns['a']))
        {
            $activity = [];
            $sql_a = $sql;

            $ref_unix = time() - ($activity_days * 86400);
            $ref_datetime = \DateTimeImmutable::createFromFormat('U', (string) $ref_unix);

            $sql_a['activity'] = $sql_map;
            $sql_a['activity']['where'][] = 't.created_at > ?';
            $sql_a['activity']['params'][] = $ref_datetime;
            $sql_a['activity']['types'][] = Types::DATETIME_IMMUTABLE;

            $activity_filter_code = trim($activity_filter_code);

            if ($activity_filter_code)
            {
                [$code_only_activity_filter_code] = explode(' ', $activity_filter_code);

                $activity_filter_user_id = $db->fetchOne('select id
                    from ' . $pp->schema() . '.users
                    where code = ?',
                    [$code_only_activity_filter_code],
                    [\PDO::PARAM_STR]);

                if ($activity_filter_user_id)
                {
                    $sql_a['filter_from_user'] = $sql_map;
                    $sql_a['filter_from_user']['where'][] = 't.id_from <> ?';
                    $sql_a['filter_from_user']['params'][] = $activity_filter_user_id;
                    $sql_a['filter_from_user']['types'][] = \PDO::PARAM_INT;
                    $sql_a['filter_to_user'] = $sql_map;
                    $sql_a['filter_to_user']['where'][] = 't.id_to <> ?';
                    $sql_a['filter_to_user']['params'][] = $activity_filter_user_id;
                    $sql_a['filter_to_user']['types'][] = \PDO::PARAM_INT;
                }
            }

            $sql_a_in = $sql_a;
            unset($sql_a_in['filter_from_user']);

            $sql_a_in_where = implode(' and ', array_merge(...array_column($sql_a_in, 'where')));
            $sql_a_in_params = array_merge(...array_column($sql_a_in, 'params'));
            $sql_a_in_types = array_merge(...array_column($sql_a_in, 'types'));

            $query_in = 'select sum(t.amount),
                    count(t.id), t.id_to
                from ' . $pp->schema() . '.transactions t
                inner join ' . $pp->schema() . '.users u
                    on t.id_to = u.id
                where ' . $sql_a_in_where . '
                group by t.id_to';

            $stmt = $db->executeQuery($query_in, $sql_a_in_params, $sql_a_in_types);

            while ($row = $stmt->fetch())
            {
                $activity[$row['id_to']] ??= [
                    'trans'	    => ['total' => 0],
                    'amount'    => ['total' => 0],
                ];

                $activity[$row['id_to']]['trans']['in'] = $row['count'];
                $activity[$row['id_to']]['amount']['in'] = $row['sum'];
                $activity[$row['id_to']]['trans']['total'] += $row['count'];
                $activity[$row['id_to']]['amount']['total'] += $row['sum'];
            }

            $sql_a_out = $sql_a;
            unset($sql_a_out['filter_to_user']);

            $sql_a_out_where = implode(' and ', array_merge(...array_column($sql_a_out, 'where')));
            $sql_a_out_params = array_merge(...array_column($sql_a_out, 'params'));
            $sql_a_out_types = array_merge(...array_column($sql_a_out, 'types'));

            $query_out = 'select sum(t.amount),
                    count(t.id), t.id_from
                from ' . $pp->schema() . '.transactions t
                inner join ' . $pp->schema() . '.users u
                    on t.id_to = u.id
                where ' . $sql_a_out_where . '
                group by t.id_from';

            $stmt = $db->executeQuery($query_out, $sql_a_out_params, $sql_a_out_types);

            while ($row = $stmt->fetch())
            {
                $activity[$row['id_from']] ??= [
                    'trans'	    => ['total' => 0],
                    'amount'    => ['total' => 0],
                ];

                $activity[$row['id_from']]['trans']['out'] = $row['count'];
                $activity[$row['id_from']]['amount']['out'] = $row['sum'];
                $activity[$row['id_from']]['trans']['total'] += $row['count'];
                $activity[$row['id_from']]['amount']['total'] += $row['sum'];
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

        $fc1 = '';
        $fc2 = '';
        $fc3 = '';

        foreach ($columns as $group => $ary)
        {
            if ($group === 'p')
            {
                continue;
            }

            if ($group === 'c')
            {
                $fc2 .= '<h3>Contacten</h3>';
            }
            else if ($group === 'd')
            {
                $fc2 .= '<h3>Afstand</h3>';
                $fc2 .= '<p>Tussen eigen adres en adres van gebruiiker. ';
                $fc2 .= 'De kolom wordt niet getoond wanneer het eigen adres ';
                $fc2 .= 'niet ingesteld is.</p>';
            }
            else if ($group === 'mollie')
            {
                $fc2 .= '<h3>Mollie (EUR)</h3>';
                $fc2 .= '<p>Status van het laatste betaalverzoek.</p>';
            }
            else if ($group === 'a')
            {
                $fc3 .= '<h3>Transacties/activiteit</h3>';

                $fc3 .= '<div class="form-group">';
                $fc3 .= '<label for="p_activity_days" ';
                $fc3 .= 'class="control-label">';
                $fc3 .= 'In periode';
                $fc3 .= '</label>';
                $fc3 .= '<div class="input-group">';
                $fc3 .= '<span class="input-group-addon">';
                $fc3 .= 'dagen';
                $fc3 .= '</span>';
                $fc3 .= '<input type="number" ';
                $fc3 .= 'id="p_activity_days" ';
                $fc3 .= 'name="sh[p][a][days]" ';
                $fc3 .= 'value="';
                $fc3 .= $activity_days;
                $fc3 .= '" ';
                $fc3 .= 'size="4" min="1" class="form-control">';
                $fc3 .= '</div>';
                $fc3 .= '</div>';

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

                $fc3 .= '<div class="form-group">';
                $fc3 .= '<label for="p_activity_filter_code" ';
                $fc3 .= 'class="control-label">';
                $fc3 .= 'Exclusief tegenpartij';
                $fc3 .= '</label>';
                $fc3 .= '<div class="input-group">';
                $fc3 .= '<span class="input-group-addon">';
                $fc3 .= '<i class="fa fa-user"></i>';
                $fc3 .= '</span>';
                $fc3 .= '<input type="text" ';
                $fc3 .= 'name="sh[p][a][code]" ';
                $fc3 .= 'id="p_activity_filter_code" ';
                $fc3 .= 'value="';
                $fc3 .= $activity_filter_code;
                $fc3 .= '" ';
                $fc3 .= 'placeholder="Account Code" ';
                $fc3 .= 'class="form-control" ';
                $fc3 .= 'data-typeahead="';

                $fc3 .= $typeahead_service->str([
                    'filter'		=> 'accounts',
                    'newuserdays'	=> $config_service->get('newuserdays', $pp->schema()),
                ]);

                $fc3 .= '">';
                $fc3 .= '</div>';
                $fc3 .= '</div>';

                foreach ($ary as $a_type => $a_ary)
                {
                    foreach($a_ary as $key => $lbl)
                    {
                        $checkbox_name = 'sh[' . $group . '][' . $a_type . '][' . $key . ']';

                        $fc3 .= strtr(BulkCnst::TPL_CHECKBOX, [
                            '%name%'    => $checkbox_name,
                            '%attr%'    => isset($show_columns[$group][$a_type][$key]) ? ' checked' : '',
                            '%label%'   => $lbl,
                        ]);
                    }
                }

                continue;
            }
            else if ($group === 'm')
            {
                $fc3 .= '<h3>Vraag en aanbod</h3>';
            }

            foreach ($ary as $key => $lbl)
            {
                $checkbox_name = 'sh[' . $group . '][' . $key . ']';

                $lbl_plus = '';

                if ($key === 'adr')
                {
                    $lbl_plus .= ', split door teken: ';
                    $lbl_plus .= '<input type="text" ';
                    $lbl_plus .= 'name="sh[p][c][adr_split]" ';
                    $lbl_plus .= 'size="1" value="';
                    $lbl_plus .= $adr_split;
                    $lbl_plus .= '">';
                }

                if ($key === 'balance_date')
                {
                    $lbl_plus .= '<div class="input-group">';
                    $lbl_plus .= '<span class="input-group-addon">';
                    $lbl_plus .= '<i class="fa fa-calendar"></i>';
                    $lbl_plus .= '</span>';
                    $lbl_plus .= '<input type="text" ';
                    $lbl_plus .= 'class="form-control" ';
                    $lbl_plus .= 'name="sh[p][u][balance_date]" ';
                    $lbl_plus .= 'data-provide="datepicker" ';
                    $lbl_plus .= 'data-date-format="';
                    $lbl_plus .= $date_format_service->datepicker_format($pp->schema());
                    $lbl_plus .= '" ';
                    $lbl_plus .= 'data-date-language="nl" ';
                    $lbl_plus .= 'data-date-today-highlight="true" ';
                    $lbl_plus .= 'data-date-autoclose="true" ';
                    $lbl_plus .= 'data-date-enable-on-readonly="false" ';
                    $lbl_plus .= 'data-date-end-date="0d" ';
                    $lbl_plus .= 'data-date-orientation="bottom" ';
                    $lbl_plus .= 'placeholder="';
                    $lbl_plus .= $date_format_service->datepicker_placeholder($pp->schema());
                    $lbl_plus .= '" ';
                    $lbl_plus .= 'value="';
                    $lbl_plus .= $balance_date;
                    $lbl_plus .= '">';
                    $lbl_plus .= '</div>';

                    $columns['u']['balance_date'] = 'Saldo op ' . $balance_date;
                }

                $chckbx = strtr(BulkCnst::TPL_CHECKBOX, [
                    '%name%'    => $checkbox_name,
                    '%attr%'    => isset($show_columns[$group][$key]) ? ' checked' : '',
                    '%label%'   => $lbl .  $lbl_plus,
                ]);

                switch ($group)
                {
                    case 'u':
                        $fc1 .= $chckbx;
                    break;
                    case 'c':
                    case 'd':
                    case 'mollie':
                        $fc2 .= $chckbx;
                    break;
                    case 'm':
                    case 'a':
                        $fc3 .= $chckbx;
                    break;
                }
            }
        }

        if ($fc3 === '')
        {
            $f_col .= '<div class="col-md-6">';
            $f_col .= $fc1;
            $f_col .= '</div>';
            $f_col .= '<div class="col-md-6">';
            $f_col .= $fc2;
            $f_col .= '</div>';
        }
        else
        {
            $f_col .= '<div class="col-md-4">';
            $f_col .= $fc1;
            $f_col .= '</div>';
            $f_col .= '<div class="col-md-4">';
            $f_col .= $fc2;
            $f_col .= '</div>';
            $f_col .= '<div class="col-md-4">';
            $f_col .= $fc3;
            $f_col .= '</div>';
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
            'balance'	    => true,
            'balance_date'	=> true,
        ];

        $date_keys = [
            'birthday'      => true,
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
            else if ($group === 'mollie')
            {
                foreach($ary as $key => $one)
                {
                    $out .= '<th>';
                    $out .= $columns[$group][$key];
                    $out .= '</th>';
                }
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

        foreach($users as $id => $u)
        {
            if (($pp->is_user() || $pp->is_guest())
                && ($u['status'] === 1 || $u['status'] === 2))
            {
                $can_link = true;
            }

            $row_stat = $u['status'];

            if (isset($u['adate'])
                && $u['status'] === 1
                && $new_user_treshold->getTimestamp() < strtotime($u['adate'] . ' UTC'))
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
                    $out .= isset($date_keys[$key]) && isset($u[$key]) ? ' data-value="' . $u[$key] . '"' : '';
                    $out .= '>';

                    $td = '';

                    if (isset($link_user_keys[$key]))
                    {
                        if ($can_link)
                        {
                            $td .= $link_render->link_no_attr('users_show', $pp->ary(),
                                ['id' => $u['id'], 'status' => $status], $u[$key] ?: '**leeg**');
                        }
                        else
                        {
                            $td .= htmlspecialchars($u[$key], ENT_QUOTES);
                        }
                    }
                    else if (isset($date_keys[$key]))
                    {
                        if (isset($u[$key]) && $u[$key])
                        {
                            $td .= $date_format_service->get($u[$key], 'day', $pp->schema());
                        }
                        else
                        {
                            $td .= '&nbsp;';
                        }
                    }
                    else if ($key === 'full_name')
                    {
                        if ($item_access_service->is_visible($u['full_name_access']))
                        {
                            if ($can_link)
                            {
                                $td .= $link_render->link_no_attr('users_show', $pp->ary(),
                                    ['id' => $u['id'], 'status' => $status], $u['full_name']);
                            }
                            else
                            {
                                $td .= htmlspecialchars($u['full_name'], ENT_QUOTES);
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
                        $td .= htmlspecialchars((string) ($u[$key] ?? ''));
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

            if (isset($show_columns['mollie']))
            {
                foreach ($show_columns['mollie'] as $key => $one)
                {
                    $out .= '<td>';

                    if (isset($mollie_ary[$id]))
                    {
                        $out .= '<span title="';
                        $out .= htmlspecialchars($mollie_ary[$id]['description'], ENT_QUOTES);
                        $out .= "\n";
                        $out .= 'EUR ' . strtr($mollie_ary[$id]['amount'], '.', ',');
                        $out .= "\n";
                        $out .= ' @';
                        $out .= $date_format_service->get($mollie_ary[$id]['created_at'], 'day', $pp->schema());
                        $out .= '" ';
                        $out .= 'class="label label-';

                        if ($mollie_ary[$id]['is_canceled'])
                        {
                            $out .= 'default">geannuleerd';
                        }
                        else if ($mollie_ary[$id]['is_paid'])
                        {
                            $out .= 'success">betaald';
                        }
                        else
                        {
                            $out .= 'warning">open';
                        }

                        $out . '</span>';
                    }
                    else
                    {
                        $out .= '&nbsp;';
                    }

                    $out .= '</td>';
                }
            }

            if (isset($show_columns['m']))
            {
                foreach($show_columns['m'] as $key => $one)
                {
                    $out .= '<td>';

                    if (isset($msgs_count[$id][$key]))
                    {
                        $out .= $link_render->link_no_attr($vr->get('messages'), $pp->ary(), [
                                'f'	=> array_merge([
                                    'uid' 	=> $id,
                                ], $message_type_filter[$key]),
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

            foreach ($user_tabs as $k => $t)
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

            foreach($user_tabs as $k => $t)
            {
                if (!$transactions_enabled && in_array($k, ['min_limit', 'max_limit']))
                {
                    continue;
                }

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
                        $tpl = BulkCnst::TPL_INPUT_FA;
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
                        '%value%'       => '',
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

        return $this->render('users/users_list.html.twig', [
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
        $btn_nav_render->view('users_list', $pp_ary,
            $params, 'Lijst', 'align-justify',
            $matched_route === 'users_list');

        $btn_nav_render->view('users_tiles', $pp_ary,
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
                'sql'	=> [
                    'where'     => ['u.status in (1, 2)'],
                ],
                'st'	=> [1, 2],
            ],
            'new'		=> [
                'lbl'	=> 'Instappers',
                'sql'	=> [
                    'where'     => ['u.status = 1 and u.adate > ?'],
                    'params'    => [$new_user_treshold],
                    'types'     => [Types::DATETIME_IMMUTABLE],
                ],
                'cl'	=> 'success',
                'st'	=> 3,
            ],
            'leaving'	=> [
                'lbl'	=> 'Uitstappers',
                'sql'	=> [
                    'where'     => ['u.status = 2'],
                ],
                'cl'	=> 'danger',
                'st'	=> 2,
            ],
        ];

        if ($pp->is_admin())
        {
            $status_def_ary = $status_def_ary + [
                'inactive'	=> [
                    'lbl'	=> 'Inactief',
                    'sql'	=> [
                        'where'     => ['u.status = 0'],
                    ],
                    'cl'	=> 'inactive',
                    'st'	=> 0,
                ],
                'ip'		=> [
                    'lbl'	=> 'Info-pakket',
                    'sql'	=> [
                        'where'     => ['u.status = 5'],
                    ],
                    'cl'	=> 'warning',
                    'st'	=> 5,
                ],
                'im'		=> [
                    'lbl'	=> 'Info-moment',
                    'sql'	=> [
                        'where'     => ['u.status = 6'],
                    ],
                    'cl'	=> 'info',
                    'st'	=> 6
                ],
                'extern'	=> [
                    'lbl'	=> 'Extern',
                    'sql'	=> [
                        'where'     => ['u.status = 7'],
                    ],
                    'cl'	=> 'extern',
                    'st'	=> 7,
                ],
                'all'		=> [
                    'lbl'	=> 'Alle',
                    'sql'	=> [],
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
        $status_def_ary = self::get_status_def_ary($config_service, $pp);

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

        foreach ($status_def_ary as $k => $tab)
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
