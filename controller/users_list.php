<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use service\item_access;
use render\btn_nav;
use render\link;
use render\heading;
use cnst\access as cnst_access;
use cnst\status as cnst_status;
use cnst\role as cnst_role;
use cnst\bulk as cnst_bulk;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class users_list
{
    const TPL_CHECKBOX_ITEM = '<label for="su[%1$s]">&nbsp;<input type="checkbox" name="su[%1$s]" id="su[%1$s]" value="1"%2$s>&nbsp;&nbsp;';

    public function users_list_admin(Request $request, app $app, string $status):Response
    {
        return $this->users_list($request, $app, $status);
    }

    public function users_list(Request $request, app $app, string $status):Response
    {
        $q = $request->get('q', '');
        $show_columns = $request->query->get('sh', []);

        $selected_users = $request->request->get('sel', []);
        $bulk_mail_subject = $request->request->get('bulk_mail_subject', '');
        $bulk_mail_content = $request->request->get('bulk_mail_content', '');
        $bulk_mail_cc = $request->request->get('bulk_mail_cc', '') ? true : false;
        $bulk_field = $request->request->get('bulk_field', []);
        $bulk_verify = $request->request->get('bulk_verify', []);
        $bulk_submit = $request->request->get('bulk_submit', []);

        /**
         * Begin bulk POST
         */

        if ($app['pp_admin']
            && $request->isMethod('POST')
            && count($bulk_submit) === 1)
        {
            $errors = [];

            if (count($bulk_field) > 1)
            {
                throw new BadRequestHttpException('Ongeldig formulier. Request voor meer dan één veld.');
            }

            if (count($bulk_verify) > 1)
            {
                throw new BadRequestHttpException('Ongeldig formulier. Meer dan één bevestigingsvakje.');
            }

            if ($error_token = $app['form_token']->get_error())
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

            if (!in_array($bulk_submit_action, ['cron_saldo', 'mail', 'mail_test'])
                && !isset($bulk_field_action))
            {
                throw new BadRequestHttpException('Ongeldig formulier. Waarde veld ontbreekt.');
            }

            if (in_array($bulk_submit_action, ['cron_saldo', 'mail', 'mail_test']))
            {
                $bulk_field_value = isset($bulk_field[$bulk_submit_action]);
            }
            else
            {
                $bulk_field_value = $bulk_field[$bulk_field_action];
            }

            if (in_array($bulk_submit_action, ['mail', 'mail_test']))
            {
                if (!$app['config']->get('mailenabled', $app['tschema']))
                {
                    $errors[] = 'De E-mail functies zijn niet ingeschakeld. Zie instellingen.';
                }

                if ($app['s_master'])
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
                $app['alert']->error($errors);
            }
            else
            {
                $user_ids = array_keys($selected_users);

                $users_log = '';

                $rows = $app['db']->executeQuery('select letscode, name, id
                    from ' . $app['tschema'] . '.users
                    where id in (?)',
                    [$user_ids], [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

                foreach ($rows as $row)
                {
                    $users_log .= ', ';
                    $users_log .= $app['account']->str_id($row['id'], $app['tschema'], false, true);
                }

                $users_log = ltrim($users_log, ', ');
            }

            $redirect = false;

            if (!count($errors) && $bulk_submit_action === 'fullname_access')
            {
                $bulk_fullname_access_xdb = cnst_access::TO_XDB[$bulk_field_value];

                foreach ($user_ids as $user_id)
                {
                    $app['xdb']->set('user_fullname_access', (string) $user_id, [
                        'fullname_access' => $bulk_fullname_access_xdb,
                    ], $app['tschema']);
                    $app['predis']->del($app['tschema'] . '_user_' . $user_id);
                }

                $app['monolog']->info('bulk: Set fullname_access to ' .
                    $bulk_field_value . ' for users ' .
                    $users_log, ['schema' => $app['tschema']]);

                $app['alert']->success('De zichtbaarheid van de
                    volledige naam werd aangepast.');

                $redirect = true;
            }
            else if (!count($errors)
                && cnst_bulk::USER_TABS[$bulk_submit_action]['item_access'])
            {
                [$abbrev] = explode('_', $bulk_field_action);

                $id_type_contact = $app['db']->fetchColumn('select id
                    from ' . $app['tschema'] . '.type_contact
                    where abbrev = ?', [$abbrev]);

                $flag_public = cnst_access::TO_FLAG_PUBLIC[$bulk_field_value];

                $app['db']->executeUpdate('update ' . $app['tschema'] . '.contact
                    set flag_public = ?
                    where id_user in (?) and id_type_contact = ?',
                        [$flag_public, $user_ids, $id_type_contact],
                        [\PDO::PARAM_INT, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY, \PDO::PARAM_INT]);

                $app['monolog']->info('bulk: Set ' . $bulk_field_action .
                    ' to ' . $bulk_field_value .
                    ' for users ' . $users_log,
                    ['schema' => $app['tschema']]);
                $app['alert']->success('Het veld werd aangepast.');

                $redirect = true;
            }
            else if (!count($errors)
                && $bulk_submit_action === 'cron_saldo')
            {
                $app['db']->executeUpdate('update ' . $app['tschema'] . '.users
                    set cron_saldo = ?
                    where id in (?)',
                    [$bulk_field_value, $user_ids],
                    [\PDO::PARAM_BOOL, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

                foreach ($user_ids as $user_id)
                {
                    $app['predis']->del($app['tschema'] . '_user_' . $user_id);
                }

                $log_value = $bulk_field_value ? 'on' : 'off';

                $app['monolog']->info('bulk: Set periodic mail to ' .
                    $log_value . ' for users ' .
                    $users_log,
                    ['schema' => $app['tschema']]);

                $app['intersystems']->clear_cache($app['s_schema']);

                $app['alert']->success('Het veld werd aangepast.');

                $redirect = true;
            }
            else if (!count($errors)
                && cnst_bulk::USER_TABS[$bulk_submit_action])
            {
                $store_value = $bulk_field_value;

                if ($bulk_submit_action === 'minlimit')
                {
                    $store_value = $store_value === '' ? -999999999 : $store_value;
                }

                if ($bulk_submit_action == 'maxlimit')
                {
                    $store_value = $store_value === '' ? 999999999 : $store_value;
                }

                $field_type = cnst_bulk::USER_TABS[$bulk_field]['string'] ? \PDO::PARAM_STR : \PDO::PARAM_INT;

                $app['db']->executeUpdate('update ' . $app['tschema'] . '.users
                    set ' . $bulk_submit_action . ' = ? where id in (?)',
                    [$store_value, $user_ids],
                    [$field_type, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

                foreach ($user_ids as $user_id)
                {
                    $app['predis']->del($app['tschema'] . '_user_' . $user_id);
                }

                if ($bulk_field == 'status')
                {
                    $app['thumbprint_accounts']->delete('active', $app['pp_ary'], $app['tschema']);
                    $app['thumbprint_accounts']->delete('extern', $app['pp_ary'], $app['tschema']);
                }

                $app['monolog']->info('bulk: Set ' . $bulk_submit_action .
                    ' to ' . $store_value .
                    ' for users ' . $users_log,
                    ['schema' => $app['tschema']]);

                $app['intersystems']->clear_cache($app['tschema']);

                $app['alert']->success('Het veld werd aangepast.');

                $redirect = true;
            }
            else if (!count($errors)
                && in_array($bulk_submit_action, ['mail', 'mail_test']))

            {
                if ($bulk_submit_action === 'mail_test')
                {
                    $sel_ary = [$app['s_id'] => true];
                    $user_ids = [$app['s_id']];
                }
                else
                {
                    $sel_ary = $selected_users;
                }

                $alert_users_sent_ary = $mail_users_sent_ary = [];

                $config_htmlpurifier = \HTMLPurifier_Config::createDefault();
                $config_htmlpurifier->set('Cache.DefinitionImpl', null);
                $htmlpurifier = new \HTMLPurifier($config_htmlpurifier);
                $bulk_mail_content = $htmlpurifier->purify($bulk_mail_content);

                $sel_users = $app['db']->executeQuery('select u.*, c.value as mail
                    from ' . $app['tschema'] . '.users u, ' .
                        $app['tschema'] . '.contact c, ' .
                        $app['tschema'] . '.type_contact tc
                    where u.id in (?)
                        and u.id = c.id_user
                        and c.id_type_contact = tc.id
                        and tc.abbrev = \'mail\'',
                        [$user_ids], [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

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

                    foreach (cnst_bulk::USER_TPL_VARS as $key => $val)
                    {
                        $vars[$key] = $sel_user[$val];
                    }

                    $app['queue.mail']->queue([
                        'schema'			=> $app['tschema'],
                        'to' 				=> $app['mail_addr_user']->get($sel_user['id'], $app['tschema']),
                        'pre_html_template' => $bulk_mail_content,
                        'reply_to' 			=> $app['mail_addr_user']->get($app['s_id'], $app['tschema']),
                        'vars'				=> $vars,
                        'template'			=> 'skeleton',
                    ], random_int(1000, 4000));

                    $alert_users_sent_ary[] = $app['account']->link($sel_user['id'], $app['pp_ary']);
                    $mail_users_sent_ary[] = $app['account']->link_url($sel_user['id'], $app['pp_ary']);
                }

                if (count($alert_users_sent_ary))
                {
                    $msg_users_sent = 'E-mail verzonden naar ';
                    $msg_users_sent .= count($alert_users_sent_ary);
                    $msg_users_sent .= ' ';
                    $msg_users_sent .= count($alert_users_sent_ary) > 1 ? 'accounts' : 'account';
                    $msg_users_sent .= ':';
                    $alert_users_sent = $msg_users_sent . '<br>';
                    $alert_users_sent .= implode('<br>', $alert_users_sent_ary);

                    $app['alert']->success($alert_users_sent);
                }
                else
                {
                    $app['alert']->warning('Geen E-mails verzonden.');
                }

                if (count($sel_ary))
                {
                    $msg_missing_users = 'Naar volgende gebruikers werd geen
                        E-mail verzonden wegens ontbreken van E-mail adres:';

                    $alert_missing_users = $msg_missing_users . '<br>';
                    $mail_missing_users = $msg_missing_users . '<br />';

                    foreach ($sel_ary as $warning_user_id => $dummy)
                    {
                        $alert_missing_users .= $app['account']->link($warning_user_id, $app['pp_ary']);
                        $alert_missing_users .= '<br>';

                        $mail_missing_users .= $app['account']->link_url($warning_user_id, $app['pp_ary']);
                        $mail_missing_users .= '<br />';
                    }

                    $app['alert']->warning($alert_missing_users);
                }

                if ($bulk_mail_cc)
                {
                    $vars = [
                        'subject'	=> 'Kopie: ' . $bulk_mail_subject,
                    ];

                    foreach (cnst_bulk::USER_TPL_VARS as $key => $trans)
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

                    $app['queue.mail']->queue([
                        'schema'			=> $app['tschema'],
                        'to' 				=> $app['mail_addr_user']->get($app['s_id'], $app['tschema']),
                        'template'			=> 'skeleton',
                        'pre_html_template'	=> $mail_users_info . $bulk_mail_content,
                        'vars'				=> $vars,
                    ], 8000);

                    $app['monolog']->debug('#bulk mail:: ' .
                        $mail_users_info . $bulk_mail_content,
                        ['schema' => $app['tschema']]);
                }

                if ($bulk_submit_action === 'mail')
                {
                    $redirect = true;
                }
            }

            if ($redirect)
            {
                $app['link']->redirect($app['r_users'], $app['pp_ary'], []);
            }
        }

        /**
         * End bulk POST
         */

        $status_def_ary = self::get_status_def_ary($app['pp_admin'], $app['new_user_treshold']);

        $sql_bind = [];

        if (isset($status_def_ary[$status]['sql_bind']))
        {
            $sql_bind[] = $status_def_ary[$status]['sql_bind'];
        }

        $params = ['status'	=> $status];

        $ref_geo = [];

        $type_contact = $app['db']->fetchAll('select id, abbrev, name
            from ' . $app['tschema'] . '.type_contact');

        $columns = [
            'u'		=> [
                'letscode'		=> 'Code',
                'name'			=> 'Naam',
                'fullname'		=> 'Volledige naam',
                'postcode'		=> 'Postcode',
                'accountrole'	=> 'Rol',
                'saldo'			=> 'Saldo',
                'saldo_date'	=> 'Saldo op ',
                'minlimit'		=> 'Min',
                'maxlimit'		=> 'Max',
                'comments'		=> 'Commentaar',
                'hobbies'		=> 'Hobbies/interesses',
            ],
        ];

        if ($app['pp_admin'])
        {
            $columns['u'] += [
                'admincomment'	=> 'Admin commentaar',
                'cron_saldo'	=> 'Periodieke Overzichts E-mail',
                'cdate'			=> 'Gecreëerd',
                'mdate'			=> 'Aangepast',
                'adate'			=> 'Geactiveerd',
                'lastlogin'		=> 'Laatst ingelogd',
            ];
        }

        foreach ($type_contact as $tc)
        {
            $columns['c'][$tc['abbrev']] = $tc['name'];
        }

        if (!$app['s_elas_guest'])
        {
            $columns['d'] = [
                'distance'	=> 'Afstand',
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
            'total'		=> '',
        ];

        $columns['a'] = [
            'trans'		=> [
                'in'	=> 'Transacties in',
                'out'	=> 'Transacties uit',
                'total'	=> 'Transacties totaal',
            ],
            'amount'	=> [
                'in'	=> $app['config']->get('currency', $app['tschema']) . ' in',
                'out'	=> $app['config']->get('currency', $app['tschema']) . ' uit',
                'total'	=> $app['config']->get('currency', $app['tschema']) . ' totaal',
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
                'saldo_date'	=> '.',
            ],
        ];

        $session_users_columns_key = 'users_columns_';
        $session_users_columns_key .= $app['pp_role'];
        $session_users_columns_key .= $app['s_elas_guest'] ? '_elas' : '';

        if (count($show_columns))
        {
            $show_columns = array_intersect_key_recursive($show_columns, $columns);

            $app['session']->set($session_users_columns_key, $show_columns);
        }
        else
        {
            if ($app['pp_admin'] || $app['s_guest'])
            {
                $preset_columns = [
                    'u'	=> [
                        'letscode'	=> 1,
                        'name'		=> 1,
                        'postcode'	=> 1,
                        'saldo'		=> 1,
                    ],
                ];
            }
            else
            {
                $preset_columns = [
                    'u' => [
                        'letscode'	=> 1,
                        'name'		=> 1,
                        'postcode'	=> 1,
                        'saldo'		=> 1,
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

            if ($app['s_elas_guest'])
            {
                unset($columns['d']['distance']);
            }

            $show_columns = $app['session']->get($session_users_columns_key) ?? $preset_columns;
        }

        $adr_split = $show_columns['p']['c']['adr_split'] ?? '';
        $activity_days = $show_columns['p']['a']['days'] ?? 365;
        $activity_days = $activity_days < 1 ? 365 : $activity_days;
        $activity_filter_code = $show_columns['p']['a']['code'] ?? '';
        $saldo_date = $show_columns['p']['u']['saldo_date'] ?? '';
        $saldo_date = trim($saldo_date);

        $users = $app['db']->fetchAll('select u.*
            from ' . $app['tschema'] . '.users u
            where ' . $status_def_ary[$status]['sql'] . '
            order by u.letscode asc', $sql_bind);

    // hack eLAS compatibility (in eLAND limits can be null)

        if (isset($show_columns['u']['minlimit']) || isset($show_columns['u']['maxlimit']))
        {
            foreach ($users as &$user)
            {
                $user['minlimit'] = $user['minlimit'] === -999999999 ? '' : $user['minlimit'];
                $user['maxlimit'] = $user['maxlimit'] === 999999999 ? '' : $user['maxlimit'];
            }
        }

        if (isset($show_columns['u']['fullname']))
        {
            foreach ($users as &$user)
            {
                $user['fullname_access'] = $app['xdb']->get(
                    'user_fullname_access',
                    (string) $user['id'],
                    $app['tschema']
                )['data']['fullname_access'] ?? 'admin';

                error_log($user['fullname_access']);
            }
        }

        if (isset($show_columns['u']['saldo_date']))
        {
            if ($saldo_date)
            {
                $saldo_date_rev = $app['date_format']->reverse($saldo_date, 'min', $app['tschema']);
            }

            if ($saldo_date_rev === '' || $saldo_date == '')
            {
                $saldo_date = $app['date_format']->get('', 'day', $app['tschema']);

                array_walk($users, function(&$user, $user_id){
                    $user['saldo_date'] = $user['saldo'];
                });
            }
            else
            {
                $trans_in = $trans_out = [];
                $datetime = new \DateTime($saldo_date_rev);

                $rs = $app['db']->prepare('select id_to, sum(amount)
                    from ' . $app['tschema'] . '.transactions
                    where date <= ?
                    group by id_to');

                $rs->bindValue(1, $datetime, 'datetime');

                $rs->execute();

                while($row = $rs->fetch())
                {
                    $trans_in[$row['id_to']] = $row['sum'];
                }

                $rs = $app['db']->prepare('select id_from, sum(amount)
                    from ' . $app['tschema'] . '.transactions
                    where date <= ?
                    group by id_from');
                $rs->bindValue(1, $datetime, 'datetime');

                $rs->execute();

                while($row = $rs->fetch())
                {
                    $trans_out[$row['id_from']] = $row['sum'];
                }

                array_walk($users, function(&$user) use ($trans_out, $trans_in){
                    $user['saldo_date'] = 0;
                    $user['saldo_date'] += $trans_in[$user['id']] ?? 0;
                    $user['saldo_date'] -= $trans_out[$user['id']] ?? 0;
                });
            }
        }

        if (isset($show_columns['c']) || (isset($show_columns['d']) && !$app['s_master']))
        {
            $c_ary = $app['db']->fetchAll('select tc.abbrev,
                    c.id_user, c.value, c.flag_public
                from ' . $app['tschema'] . '.contact c, ' .
                    $app['tschema'] . '.type_contact tc, ' .
                    $app['tschema'] . '.users u
                where tc.id = c.id_type_contact ' .
                    (isset($show_columns['c']) ? '' : 'and tc.abbrev = \'adr\' ') .
                    'and c.id_user = u.id
                    and ' . $status_def_ary[$status]['sql'], $sql_bind);

            $contacts = [];

            foreach ($c_ary as $c)
            {
                $contacts[$c['id_user']][$c['abbrev']][] = [
                    'value'         => $c['value'],
                    'flag_public'   => $c['flag_public'],
                ];
            }
        }

        if (isset($show_columns['d']) && !$app['s_master'])
        {
            if (($app['s_guest'] && $app['s_schema'] && !$app['s_elas_guest'])
                || !isset($contacts[$app['s_id']]['adr']))
            {
                $my_adr = $app['db']->fetchColumn('select c.value
                    from ' . $app['s_schema'] . '.contact c, ' .
                        $app['s_schema'] . '.type_contact tc
                    where c.id_user = ?
                        and c.id_type_contact = tc.id
                        and tc.abbrev = \'adr\'', [$app['s_id']]);
            }
            else if (!$app['s_guest']
                && isset($contacts[$app['s_id']]['adr'][0]['value']))
            {
                $my_adr = trim($contacts[$app['s_id']]['adr'][0]['value']);
            }

            if (isset($my_adr))
            {
                $ref_geo = $app['cache']->get('geo_' . $my_adr);
            }
        }

        if (isset($show_columns['m']))
        {
            $msgs_count = [];

            if (isset($show_columns['m']['offers']))
            {
                $ary = $app['db']->fetchAll('select count(m.id), m.id_user
                    from ' . $app['tschema'] . '.messages m, ' .
                        $app['tschema'] . '.users u
                    where msg_type = 1
                        and m.id_user = u.id
                        and ' . $status_def_ary[$status]['sql'] . '
                    group by m.id_user', $sql_bind);

                foreach ($ary as $a)
                {
                    $msgs_count[$a['id_user']]['offers'] = $a['count'];
                }
            }

            if (isset($show_columns['m']['wants']))
            {
                $ary = $app['db']->fetchAll('select count(m.id), m.id_user
                    from ' . $app['tschema'] . '.messages m, ' .
                        $app['tschema'] . '.users u
                    where msg_type = 0
                        and m.id_user = u.id
                        and ' . $status_def_ary[$status]['sql'] . '
                    group by m.id_user', $sql_bind);

                foreach ($ary as $a)
                {
                    $msgs_count[$a['id_user']]['wants'] = $a['count'];
                }
            }

            if (isset($show_columns['m']['total']))
            {
                $ary = $app['db']->fetchAll('select count(m.id), m.id_user
                    from ' . $app['tschema'] . '.messages m, ' .
                        $app['tschema'] . '.users u
                    where m.id_user = u.id
                        and ' . $status_def_ary[$status]['sql'] . '
                    group by m.id_user', $sql_bind);

                foreach ($ary as $a)
                {
                    $msgs_count[$a['id_user']]['total'] = $a['count'];
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
                $and = ' and u.letscode <> ? ';
                $sql_bind[] = trim($code_only_activity_filter_code);
            }
            else
            {
                $and = ' and 1 = 1 ';
            }

            $trans_in_ary = $app['db']->fetchAll('select sum(t.amount),
                    count(t.id), t.id_to
                from ' . $app['tschema'] . '.transactions t, ' .
                    $app['tschema'] . '.users u
                where t.id_from = u.id
                    and t.cdate > ?' . $and . '
                group by t.id_to', $sql_bind);

            $trans_out_ary = $app['db']->fetchAll('select sum(t.amount),
                    count(t.id), t.id_from
                from ' . $app['tschema'] . '.transactions t, ' .
                    $app['tschema'] . '.users u
                where t.id_to = u.id
                    and t.cdate > ?' . $and . '
                group by t.id_from', $sql_bind);

            foreach ($trans_in_ary as $trans_in)
            {
                if (!isset($activity[$trans_in['id_to']]))
                {
                    $activity[$trans_in['id_to']] = [
                        'trans'	=> ['total' => 0],
                        'amount' => ['total' => 0],
                    ];
                }

                $activity[$trans_in['id_to']]['trans']['in'] = $trans_in['count'];
                $activity[$trans_in['id_to']]['amount']['in'] = $trans_in['sum'];
                $activity[$trans_in['id_to']]['trans']['total'] += $trans_in['count'];
                $activity[$trans_in['id_to']]['amount']['total'] += $trans_in['sum'];
            }

            foreach ($trans_out_ary as $trans_out)
            {
                if (!isset($activity[$trans_out['id_from']]))
                {
                    $activity[$trans_out['id_from']] = [
                        'trans'	=> ['total' => 0],
                        'amount' => ['total' => 0],
                    ];
                }

                $activity[$trans_out['id_from']]['trans']['out'] = $trans_out['count'];
                $activity[$trans_out['id_from']]['amount']['out'] = $trans_out['sum'];
                $activity[$trans_out['id_from']]['trans']['total'] += $trans_out['count'];
                $activity[$trans_out['id_from']]['amount']['total'] += $trans_out['sum'];
            }
        }

        if ($app['pp_admin'])
        {
            $app['btn_nav']->csv();

            $app['btn_top']->add('users_add', $app['pp_ary'],
                [], 'Gebruiker toevoegen');

            $app['btn_top']->local('#actions', 'Bulk acties', 'envelope-o');
        }

        $app['btn_nav']->columns_show();

        self::btn_nav($app['btn_nav'], $app['pp_ary'], $params, 'users_list');
        self::heading($app['heading']);

        $app['assets']->add([
            'calc_sum.js',
            'users_distance.js',
            'datepicker',
        ]);

        if ($app['pp_admin'])
        {
            $app['assets']->add([
                'summernote',
                'table_sel.js',
                'rich_edit.js',
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

                $app['typeahead']->ini($app['pp_ary'])
                    ->add('accounts', ['status' => 'active']);

                if (!$app['s_guest'])
                {
                    $app['typeahead']->add('accounts', ['status' => 'extern']);
                }

                if ($app['pp_admin'])
                {
                    $app['typeahead']->add('accounts', ['status' => 'inactive'])
                        ->add('accounts', ['status' => 'ip'])
                        ->add('accounts', ['status' => 'im']);
                }

                $f_col .= '<div class="form-group">';
                $f_col .= '<label for="p_activity_filter_letscode" ';
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

                $f_col .= $app['typeahead']->str([
                    'filter'		=> 'accounts',
                    'newuserdays'	=> $app['config']->get('newuserdays', $app['tschema']),
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

                if ($key === 'saldo_date')
                {
                    $f_col .= '<div class="input-group">';
                    $f_col .= '<span class="input-group-addon">';
                    $f_col .= '<i class="fa fa-calendar"></i>';
                    $f_col .= '</span>';
                    $f_col .= '<input type="text" ';
                    $f_col .= 'class="form-control" ';
                    $f_col .= 'name="sh[p][u][saldo_date]" ';
                    $f_col .= 'data-provide="datepicker" ';
                    $f_col .= 'data-date-format="';
                    $f_col .= $app['date_format']->datepicker_format($app['tschema']);
                    $f_col .= '" ';
                    $f_col .= 'data-date-language="nl" ';
                    $f_col .= 'data-date-today-highlight="true" ';
                    $f_col .= 'data-date-autoclose="true" ';
                    $f_col .= 'data-date-enable-on-readonly="false" ';
                    $f_col .= 'data-date-end-date="0d" ';
                    $f_col .= 'data-date-orientation="bottom" ';
                    $f_col .= 'placeholder="';
                    $f_col .= $app['date_format']->datepicker_placeholder($app['tschema']);
                    $f_col .= '" ';
                    $f_col .= 'value="';
                    $f_col .= $saldo_date;
                    $f_col .= '">';
                    $f_col .= '</div>';

                    $columns['u']['saldo_date'] = 'Saldo op ' . $saldo_date;
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
            $app['r_users'], $app['pp_ary'], $params, $app['link'],
            $app['pp_admin'], $f_col, $q, $app['new_user_treshold']
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
            'saldo'			=> true,
            'saldo_date'	=> true,
        ];

        $date_keys = [
            'cdate'			=> true,
            'mdate'			=> true,
            'adate'			=> true,
            'lastlogin'		=> true,
        ];

        $link_user_keys = [
            'letscode'		=> true,
            'name'			=> true,
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
                    $data_sort_initial = $key === 'letscode' ? ' data-sort-initial="true"' : '';

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

        $can_link = $app['pp_admin'];

        foreach($users as $u)
        {
            if (($app['s_user'] || $app['s_guest'])
                && ($u['status'] === 1 || $u['status'] === 2))
            {
                $can_link = true;
            }

            $id = $u['id'];

            $row_stat = $u['status'];

            if (isset($u['adate'])
                && $u['status'] == 1
                && $app['new_user_treshold'] < strtotime($u['adate']))
            {
                $row_stat = 3;
            }

            $first = true;

            $out .= '<tr';

            if (isset(cnst_status::CLASS_ARY[$row_stat]))
            {
                $out .= ' class="';
                $out .= cnst_status::CLASS_ARY[$row_stat];
                $out .= '"';
            }

            $out .= ' data-balance="';
            $out .= $u['saldo'];
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
                            $td .= $app['link']->link_no_attr($app['r_users_show'], $app['pp_ary'],
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
                            $td .= $app['date_format']->get($u[$key], 'day', $app['tschema']);
                        }
                        else
                        {
                            $td .= '&nbsp;';
                        }
                    }
                    else if ($key === 'fullname')
                    {
                        if ($app['pp_admin']
                            || $u['fullname_access'] === 'interlets'
                            || ($app['s_user'] && $u['fullname_access'] !== 'admin'))
                        {
                            if ($can_link)
                            {
                                $td .= $app['link']->link_no_attr($app['r_users_show'], $app['pp_ary'],
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
                    else if ($key === 'accountrole')
                    {
                        $td .= cnst_role::LABEL_ARY[$u['accountrole']];
                    }
                    else
                    {
                        $td .= htmlspecialchars((string) $u[$key]);
                    }

                    if ($app['pp_admin'] && $first)
                    {
                        $out .= strtr(cnst_bulk::TPL_CHECKBOX_ITEM, [
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

                        $out .= self::get_contacts_str($app['item_access'], [[
                            'value'         => $adr_1,
                            'flag_public'   => $contacts[$id]['adr'][0][1]]],
                        'adr');

                        $out .= '</td><td>';

                        $out .= self::get_contacts_str($app['item_access'], [[
                            'value'         => $adr_2,
                            'flag_public'   => $contacts[$id]['adr'][0][1]]],
                        'adr');
                    }
                    else if (isset($contacts[$id][$key]))
                    {
                        $out .= self::get_contacts_str($app['item_access'], $contacts[$id][$key], $key);
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
                $out .= '<td data-value="5000"';

                $adr_ary = $contacts[$id]['adr'][0] ?? [];

                if (isset($adr_ary['flag_public']))
                {
                    if ($app['item_access']->is_visible_flag_public($adr_ary['flag_public']))
                    {
                        if (count($adr_ary) && $adr_ary['value'])
                        {
                            $geo = $app['cache']->get('geo_' . $adr_ary['value']);

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
                        $out .= $app['link']->link_no_attr($app['r_messages'], $app['pp_ary'],
                            [
                                'f'	=> [
                                    'uid' 	=> $id,
                                    'type' 	=> $message_type_filter[$key],
                                ],
                            ],
                            $msgs_count[$id][$key]);
                    }

                    $out .= '</td>';
                }
            }

            if (isset($show_columns['a']))
            {
                $from_date = $app['date_format']->get_from_unix(time() - ($activity_days * 86400), 'day', $app['tschema']);

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
                                $out .= $app['link']->link_no_attr('transactions', $app['pp_ary'],
                                    [
                                        'f' => [
                                            'fcode'	=> $key === 'in' ? '' : $u['letscode'],
                                            'tcode'	=> $key === 'out' ? '' : $u['letscode'],
                                            'andor'	=> $key === 'total' ? 'or' : 'and',
                                            'fdate' => $from_date,
                                        ],
                                    ],
                                    $activity[$id][$a_key][$key]);
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
        $out .= $app['config']->get('currency', $app['tschema']);
        $out .= '</span></p>';
        $out .= '</div></div>';

        if ($app['pp_admin'] & isset($show_columns['u']))
        {
            $out .= cnst_bulk::TPL_SELECT_BUTTONS;

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

            foreach (cnst_bulk::USER_TABS as $k => $t)
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
            $out .= 'class="form-control rich-edit" ';
            $out .= 'id="bulk_mail_content" rows="8" ';
            $out .= 'data-template-vars="';
            $out .= implode(',', array_keys(cnst_bulk::USER_TPL_VARS));
            $out .= '" ';
            $out .= 'required>';
            $out .= $bulk_mail_content;
            $out .= '</textarea>';
            $out .= '</div>';

            $out .= strtr(cnst_bulk::TPL_CHECKBOX, [
                '%name%'    => 'bulk_mail_cc',
                '%label%'   => 'Stuur een kopie met verzendinfo naar mijzelf',
                '%attr%'    => $bulk_mail_cc ? ' checked' : '',
            ]);

            $out .= strtr(cnst_bulk::TPL_CHECKBOX, [
                '%name%'    => 'bulk_verify[mail]',
                '%label%'   => 'Ik heb mijn bericht nagelezen en nagekeken dat de juiste gebruikers geselecteerd zijn.',
                '%attr%'    => ' required',
            ]);

            $out .= '<input type="submit" value="Zend test E-mail naar mijzelf" ';
            $out .= 'name="bulk_submit[mail_test]" class="btn btn-default">&nbsp;';
            $out .= '<input type="submit" value="Verzend" name="bulk_submit[mail]" ';
            $out .= 'class="btn btn-default">';

            $out .= $app['form_token']->get_hidden_input();
            $out .= '</form>';
            $out .= '</div>';

            foreach(cnst_bulk::USER_TABS as $k => $t)
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
                    $out .= $app['item_access']->get_radio_buttons($bulk_field_name);
                }
                else
                {
                    $options = '';

                    if (isset($t['options']))
                    {
                        $tpl = cnst_bulk::TPL_SELECT;
                        $options = $app['select']->get_options($t['options'], '');
                    }
                    else if (isset($t['type'])
                        && $t['type'] === 'checkbox')
                    {
                        $tpl = cnst_bulk::TPL_CHECKBOX;
                    }
                    else
                    {
                        $tpl = cnst_bulk::TPL_INPUT;
                    }

                    $out .= strtr($tpl, [
                        '%name%'        => $bulk_field_name,
                        '%label%'       => $t['lbl'],
                        '%type%'        => $t['type'] ?? '',
                        '%options%'     => $options,
                        '%required%'    => $t['required'] ? ' required' : '',
                        '%fa%'          => $t['fa'] ?? '',
                        '%attr%'        => $t['attr'] ?? '',
                    ]);
                }

                $out .= strtr(cnst_bulk::TPL_CHECKBOX, [
                    '%name%'    => 'bulk_verify[' . $k  . ']',
                    '%label%'   => 'Ik heb de ingevulde waarde nagekeken en dat de juiste gebruikers geselecteerd zijn.',
                    '%attr%'    => ' required',
                ]);

                $out .= '<input type="submit" value="Veld aanpassen" ';
                $out .= 'name="bulk_submit[' . $k . ']" class="btn btn-primary">';
                $out .= $app['form_token']->get_hidden_input();
                $out .= '</form>';

                $out .= '</div>';
            }

            $out .= '<div class="clearfix"></div>';
            $out .= '</div>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $app['tpl']->add($out);
        $app['tpl']->menu('users');

        return $app['tpl']->get();
    }

    static public function btn_nav(
        btn_nav $btn_nav,
        array $pp_ary,
        array $params,
        string $matched_route
    ):void
    {
        $admin_suffix = $pp_ary['role_short'] === 'a' ? '_admin' : '';

        $btn_nav->view('users_list' . $admin_suffix, $pp_ary,
            $params, 'Lijst', 'align-justify',
            $matched_route === 'users_list');

        $btn_nav->view('users_tiles' . $admin_suffix, $pp_ary,
            $params, 'Tegels met foto\'s', 'th',
            $matched_route === 'users_tiles');

        unset($params['status']);

        $btn_nav->view('users_map', $pp_ary,
            $params, 'Kaart', 'map-marker',
            $matched_route === 'users_map');
    }

    static public function heading(heading $heading):void
    {
        $heading->add('Gebruikers');
        $heading->fa('users');
    }

    static public function get_status_def_ary(
        bool $pp_admin,
        int $new_user_treshold
    ):array
    {
        $status_def_ary = [
            'active'	=> [
                'lbl'	=> $pp_admin ? 'Actief' : 'Alle',
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

        if ($pp_admin)
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
        string $r_users,
        array $pp_ary,
        array $params,
        link $link,
        bool $pp_admin,
        string $before,
        string $q,
        int $new_user_treshold
    ):string
    {
        $out = '';

        $out .= '<form method="get">';

        foreach ($params as $k => $v)
        {
            $out .= '<input type="hidden" name="' . $k . '" value="' . $v . '">';
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

        foreach (self::get_status_def_ary($pp_admin, $new_user_treshold) as $k => $tab)
        {
            $nav_params['status'] = $k;

            $out .= '<li';
            $out .= $params['status'] === $k ? ' class="active"' : '';
            $out .= '>';

            $class_ary = isset($tab['cl']) ? ['class' => 'bg-' . $tab['cl']] : [];

            $out .= $link->link($r_users, $pp_ary,
                $nav_params, $tab['lbl'], $class_ary);

            $out .= '</li>';
        }

        $out .= '</ul>';

        return $out;
    }

    public static function get_contacts_str(
        item_access $item_access,
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
                if ($item_access->is_visible_flag_public($contact['flag_public']))
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
}
