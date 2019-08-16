<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use cnst\status as cnst_status;
use cnst\role as cnst_role;

class users_edit
{
    public function users_edit(Request $request, app $app, string $status, int $id):Response
    {
        return $this->users_show_admin($request, $app, $status, $id);
    }

    public function users_edit_admin(Request $request, app $app, int $id):Response
    {





        $app['tpl']->add($out);
        $app['tpl']->menu('users');

        return $app['tpl']->get();
    }

    public function get_form():string
    {
// add - edit block
        if ($add && !$app['s_admin'])
        {
            $app['alert']->error('Je hebt geen rechten om
                een gebruiker toe te voegen.');

            $app['link']->redirect('users', $app['pp_ary'], []);
        }

        $s_owner =  !$app['s_guest']
            && $app['s_system_self']
            && $edit
            && $app['s_id']
            && $edit == $app['s_id'];

        if ($edit && !$app['s_admin'] && !$s_owner)
        {
            $app['alert']->error('Je hebt geen rechten om
                deze gebruiker aan te passen.');

            $app['link']->redirect('users', $app['pp_ary'], ['id' => $edit]);
        }

        if ($app['s_admin'])
        {
            $username_edit = $fullname_edit = true;
        }
        else if ($s_owner)
        {
            $username_edit = $app['config']->get('users_can_edit_username', $app['tschema']);
            $fullname_edit = $app['config']->get('users_can_edit_fullname', $app['tschema']);
        }
        else
        {
            $username_edit = $fullname_edit = false;
        }

        if ($app['request']->isMethod('POST'))
        {
            $user = [
                'postcode'		=> trim($_POST['postcode']),
                'birthday'		=> trim($_POST['birthday']) ?: null,
                'hobbies'		=> trim($_POST['hobbies']),
                'comments'		=> trim($_POST['comments']),
                'cron_saldo'	=> isset($_POST['cron_saldo']) ? 1 : 0,
                'lang'			=> 'nl'
            ];

            if ($app['s_admin'])
            {
                // hack eLAS compatibility (in eLAND limits can be null)
                $minlimit = trim($_POST['minlimit']);
                $maxlimit = trim($_POST['maxlimit']);

                $minlimit = $minlimit === '' ? -999999999 : $minlimit;
                $maxlimit = $maxlimit === '' ? 999999999 : $maxlimit;

                $user += [
                    'letscode'		=> trim($_POST['letscode']),
                    'accountrole'	=> $_POST['accountrole'],
                    'status'		=> $_POST['status'],
                    'admincomment'	=> trim($_POST['admincomment']),
                    'minlimit'		=> $minlimit,
                    'maxlimit'		=> $maxlimit,
                    'presharedkey'	=> trim($_POST['presharedkey']),
                ];

                $contact = $_POST['contact'];
                $notify = $_POST['notify'];
                $password = trim($_POST['password']);

                $mail_unique_check_sql = 'select count(c.value)
                        from ' . $app['tschema'] . '.contact c, ' .
                            $app['tschema'] . '.type_contact tc, ' .
                            $app['tschema'] . '.users u
                        where c.id_type_contact = tc.id
                            and tc.abbrev = \'mail\'
                            and c.value = ?
                            and c.id_user = u.id
                            and u.status in (1, 2)';

                if ($edit)
                {
                    $mail_unique_check_sql .= ' and u.id <> ?';
                }

                $mailadr = false;

                $st = $app['db']->prepare($mail_unique_check_sql);

                foreach ($contact as $key => $c)
                {
                    $access_contact = $app['request']->request->get('contact_access_' . $key);

                    if ($c['value'] && !$access_contact)
                    {
                        $errors[] = 'Vul een zichtbaarheid in.';
                    }

                    $contact[$key]['flag_public'] = $app['item_access']->get_flag_public($access_contact);
                }

                foreach ($contact as $key => $c)
                {
                    if ($c['abbrev'] == 'mail')
                    {
                        $mailadr = trim($c['value']);

                        if ($mailadr)
                        {
                            if (!filter_var($mailadr, FILTER_VALIDATE_EMAIL))
                            {
                                $errors[] =  $mailadr . ' is geen geldig email adres.';
                            }

                            $st->bindValue(1, $mailadr);

                            if ($edit)
                            {
                                $st->bindValue(2, $edit);
                            }

                            $st->execute();

                            $row = $st->fetch();

                            $warning = 'Omdat deze gebruikers niet meer een uniek E-mail adres hebben zullen zij ';
                            $warning .= 'niet meer zelf hun paswoord kunnnen resetten of kunnen inloggen met ';
                            $warning .= 'E-mail adres. Zie ';
                            $warning .= $app['link']->link_no_attr('status', $app['pp_ary'], [], 'Status');

                            $warning_2 = '';

                            if ($row['count'] == 1)
                            {
                                $warning_2 .= 'Waarschuwing: E-mail adres ' . $mailadr;
                                $warning_2 .= ' bestaat al onder de actieve gebruikers. ';
                            }
                            else if ($row['count'] > 1)
                            {
                                $warning_2 .= 'Waarschuwing: E-mail adres ' . $mailadr;
                                $warning_2 .= ' bestaat al ' . $row['count'];
                                $warning_2 .= ' maal onder de actieve gebruikers. ';
                            }

                            if ($warning_2)
                            {
                                $app['alert']->warning($warning_2 . $warning);
                            }
                        }
                    }
                }

                if ($user['status'] == 1 || $user['status'] == 2)
                {
                    if (!$mailadr)
                    {
                        $err = 'Waarschuwing: Geen E-mail adres ingevuld. ';
                        $err .= 'De gebruiker kan geen berichten en notificaties ';
                        $err .= 'ontvangen en zijn/haar paswoord niet resetten.';
                        $app['alert']->warning($err);
                    }
                }

                $letscode_sql = 'select letscode
                    from ' . $app['tschema'] . '.users
                    where letscode = ?';
                $letscode_sql_params = [$user['letscode']];
            }

            if ($username_edit)
            {
                $user['login'] = $user['name'] = trim($_POST['name']);
            }

            if ($fullname_edit)
            {
                $user['fullname'] = trim($_POST['fullname']);
            }

            $fullname_access = $app['request']->request->get('fullname_access', '');

            $name_sql = 'select name
                from ' . $app['tschema'] . '.users
                where name = ?';
            $name_sql_params = [$user['name']];

            $fullname_sql = 'select fullname
                from ' . $app['tschema'] . '.users
                where fullname = ?';
            $fullname_sql_params = [$user['fullname']];

            if ($edit)
            {
                $letscode_sql .= ' and id <> ?';
                $letscode_sql_params[] = $edit;
                $name_sql .= 'and id <> ?';
                $name_sql_params[] = $edit;
                $fullname_sql .= 'and id <> ?';
                $fullname_sql_params[] = $edit;

                $user_prefetch = $app['user_cache']->get($edit, $app['tschema']);
            }

            if (!$fullname_access)
            {
                $errors[] = 'Vul een zichtbaarheid in voor de volledige naam.';
            }

            if ($username_edit)
            {
                if (!$user['name'])
                {
                    $errors[] = 'Vul gebruikersnaam in!';
                }
                else if ($app['db']->fetchColumn($name_sql, $name_sql_params))
                {
                    $errors[] = 'Deze gebruikersnaam is al in gebruik!';
                }
                else if (strlen($user['name']) > 50)
                {
                    $errors[] = 'De gebruikersnaam mag maximaal 50 tekens lang zijn.';
                }
            }

            if ($fullname_edit)
            {
                if (!$user['fullname'])
                {
                    $errors[] = 'Vul de Volledige Naam in!';
                }

                if ($app['db']->fetchColumn($fullname_sql, $fullname_sql_params))
                {
                    $errors[] = 'Deze Volledige Naam is al in gebruik!';
                }

                if (strlen($user['fullname']) > 100)
                {
                    $errors[] = 'De Volledige Naam mag maximaal 100 tekens lang zijn.';
                }
            }

            if ($app['s_admin'])
            {
                if (!$user['letscode'])
                {
                    $errors[] = 'Vul een Account Code in!';
                }
                else if ($app['db']->fetchColumn($letscode_sql, $letscode_sql_params))
                {
                    $errors[] = 'De Account Code bestaat al!';
                }
                else if (strlen($user['letscode']) > 20)
                {
                    $errors[] = 'De Account Code mag maximaal
                        20 tekens lang zijn.';
                }

                if (!preg_match("/^[A-Za-z0-9-]+$/", $user['letscode']))
                {
                    $errors[] = 'De Account Code kan enkel uit
                        letters, cijfers en koppeltekens bestaan.';
                }

                if (filter_var($user['minlimit'], FILTER_VALIDATE_INT) === false)
                {
                    $errors[] = 'Geef getal of niets op voor de
                        Minimum Account Limiet.';
                }

                if (filter_var($user['maxlimit'], FILTER_VALIDATE_INT) === false)
                {
                    $errors[] = 'Geef getal of niets op voor de
                        Maximum Account Limiet.';
                }

                if (strlen($user['presharedkey']) > 80)
                {
                    $errors[] = 'De Preshared Key mag maximaal
                        80 tekens lang zijn.';
                }
            }

            if ($user['birthday'])
            {
                $user['birthday'] = $app['date_format']->reverse($user['birthday'], $app['tschema']);

                if ($user['birthday'] === '')
                {
                    $errors[] = 'Fout in formaat geboortedag.';
                    $user['birthday'] = '';
                }
            }

            if (strlen($user['comments']) > 100)
            {
                $errors[] = 'Het veld Commentaar mag maximaal
                    100 tekens lang zijn.';
            }

            if (strlen($user['postcode']) > 6)
            {
                $errors[] = 'De postcode mag maximaal 6 tekens lang zijn.';
            }

            if (strlen($user['hobbies']) > 500)
            {
                $errors[] = 'Het veld hobbies en interesses mag
                    maximaal 500 tekens lang zijn.';
            }

            if ($app['s_admin'] && !$user_prefetch['adate'] && $user['status'] == 1)
            {
                if (!$password)
                {
                    $errors[] = 'Gelieve een Paswoord in te vullen.';
                }
                else if (!$app['password_strength']->get($password))
                {
                    $errors[] = 'Het Paswoord is niet sterk genoeg.';
                }
            }

            if ($error_token = $app['form_token']->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($errors))
            {
                $contact_types = [];

                $rs = $app['db']->prepare('select abbrev, id
                    from ' . $app['tschema'] . '.type_contact');

                $rs->execute();

                while ($row = $rs->fetch())
                {
                    $contact_types[$row['abbrev']] = $row['id'];
                }

                if ($add)
                {
                    $user['creator'] = $app['s_master'] ? 0 : $app['s_id'];

                    $user['cdate'] = gmdate('Y-m-d H:i:s');

                    if ($user['status'] == 1)
                    {
                        $user['adate'] = gmdate('Y-m-d H:i:s');
                        $user['password'] = hash('sha512', $password);
                    }
                    else
                    {
                        $user['password'] = hash('sha512', sha1(microtime()));
                    }

                    if ($app['db']->insert($app['tschema'] . '.users', $user))
                    {
                        $id = $app['db']->lastInsertId($app['tschema'] . '.users_id_seq');

                        $fullname_access_role = $app['item_access']->get_xdb($fullname_access);

                        $app['xdb']->set('user_fullname_access', $id, [
                            'fullname_access' => $fullname_access_role,
                        ], $app['tschema']);

                        $app['alert']->success('Gebruiker opgeslagen.');

                        $app['user_cache']->clear($id, $app['tschema']);
                        $user = $app['user_cache']->get($id, $app['tschema']);

                        foreach ($contact as $value)
                        {
                            if (!$value['value'])
                            {
                                continue;
                            }

                            if ($value['abbrev'] === 'adr')
                            {
                                $app['queue.geocode']->cond_queue([
                                    'adr'		=> $value['value'],
                                    'uid'		=> $id,
                                    'schema'	=> $app['tschema'],
                                ], 0);
                            }

                            $insert = [
                                'value'				=> trim($value['value']),
                                'flag_public'		=> $value['flag_public'],
                                'id_type_contact'	=> $contact_types[$value['abbrev']],
                                'id_user'			=> $id,
                            ];

                            $app['db']->insert($app['tschema'] . '.contact', $insert);
                        }

                        if ($user['status'] == 1)
                        {
                            if ($notify && $password)
                            {
                                if ($app['config']->get('mailenabled', $app['tschema']))
                                {
                                    if ($mailadr)
                                    {
                                        send_activation_mail_user($id, $password);
                                        $app['alert']->success('Een E-mail met paswoord is
                                            naar de gebruiker verstuurd.');
                                    }
                                    else
                                    {
                                        $app['alert']->warning('Er is geen E-mail met paswoord
                                            naar de gebruiker verstuurd want er is geen E-mail
                                            adres ingesteld voor deze gebruiker.');
                                    }

                                    send_activation_mail_admin($id);

                                }
                                else
                                {
                                    $app['alert']->warning('De E-mail functies zijn uitgeschakeld.
                                        Geen E-mail met paswoord naar de gebruiker verstuurd.');
                                }
                            }
                            else
                            {
                                $app['alert']->warning('Geen E-mail met paswoord naar
                                    de gebruiker verstuurd.');
                            }
                        }

                        if ($user['status'] == 2 | $user['status'] == 1)
                        {
                            delete_thumbprint('active');
                        }

                        if ($user['status'] == 7)
                        {
                            delete_thumbprint('extern');
                        }

                        $app['intersystems']->clear_cache($app['s_schema']);

                        $app['link']->redirect('users', $app['pp_ary'], ['id' => $id]);
                    }
                    else
                    {
                        $app['alert']->error('Gebruiker niet opgeslagen.');
                    }
                }
                else if ($edit)
                {
                    $user_stored = $app['user_cache']->get($edit, $app['tschema']);

                    $user['mdate'] = gmdate('Y-m-d H:i:s');

                    if (!$user_stored['adate'] && $user['status'] == 1)
                    {
                        $user['adate'] = gmdate('Y-m-d H:i:s');

                        if ($password)
                        {
                            $user['password'] = hash('sha512', $password);
                        }
                    }

                    if($app['db']->update($app['tschema'] . '.users', $user, ['id' => $edit]))
                    {

                        $fullname_access_role = $app['item_access']->get_xdb($fullname_access);

                        $app['xdb']->set('user_fullname_access', $edit, [
                            'fullname_access' => $fullname_access_role,
                        ], $app['tschema']);

                        $app['user_cache']->clear($edit, $app['tschema']);
                        $user = $app['user_cache']->get($edit, $app['tschema']);

                        $app['alert']->success('Gebruiker aangepast.');

                        if ($app['s_admin'])
                        {
                            $stored_contacts = [];

                            $rs = $app['db']->prepare('select c.id,
                                    tc.abbrev, c.value, c.flag_public
                                from ' . $app['tschema'] . '.type_contact tc, ' .
                                    $app['tschema'] . '.contact c
                                WHERE tc.id = c.id_type_contact
                                    AND c.id_user = ?');
                            $rs->bindValue(1, $edit);

                            $rs->execute();

                            while ($row = $rs->fetch())
                            {
                                $stored_contacts[$row['id']] = $row;
                            }

                            foreach ($contact as $value)
                            {
                                $stored_contact = $stored_contacts[$value['id']];

                                if (!$value['value'])
                                {
                                    if ($stored_contact)
                                    {
                                        $app['db']->delete($app['tschema'] . '.contact',
                                            ['id_user' => $edit, 'id' => $value['id']]);
                                    }
                                    continue;
                                }

                                if ($stored_contact['abbrev'] == $value['abbrev']
                                    && $stored_contact['value'] == $value['value']
                                    && $stored_contact['flag_public'] == $value['flag_public'])
                                {
                                    continue;
                                }

                                if ($value['abbrev'] === 'adr')
                                {
                                    $app['queue.geocode']->cond_queue([
                                        'adr'		=> $value['value'],
                                        'uid'		=> $edit,
                                        'schema'	=> $app['tschema'],
                                    ], 0);
                                }

                                if (!isset($stored_contact))
                                {
                                    $insert = [
                                        'id_type_contact'	=> $contact_types[$value['abbrev']],
                                        'value'				=> trim($value['value']),
                                        'flag_public'		=> $value['flag_public'],
                                        'id_user'			=> $edit,
                                    ];
                                    $app['db']->insert($app['tschema'] . '.contact', $insert);
                                    continue;
                                }

                                $contact_update = $value;

                                unset($contact_update['id'], $contact_update['abbrev'],
                                    $contact_update['name'], $contact_update['main_mail']);

                                $app['db']->update($app['tschema'] . '.contact',
                                    $contact_update,
                                    ['id' => $value['id'], 'id_user' => $edit]);
                            }

                            if ($user['status'] == 1 && !$user_prefetch['adate'])
                            {
                                if ($notify && $password)
                                {
                                    if ($app['config']->get('mailenabled', $app['tschema']))
                                    {
                                        if ($mailadr)
                                        {
                                            send_activation_mail_user($edit, $password);
                                            $app['alert']->success('E-mail met paswoord
                                                naar de gebruiker verstuurd.');
                                        }
                                        else
                                        {
                                            $app['alert']->warning('Er werd geen E-mail
                                                met passwoord naar de gebruiker verstuurd
                                                want er is geen E-mail adres voor deze
                                                gebruiker ingesteld.');
                                        }

                                        send_activation_mail_admin($edit);
                                    }
                                    else
                                    {
                                        $app['alert']->warning('De E-mail functies zijn uitgeschakeld.
                                            Geen E-mail met paswoord naar de gebruiker verstuurd.');
                                    }
                                }
                                else
                                {
                                    $app['alert']->warning('Geen E-mail met
                                        paswoord naar de gebruiker verstuurd.');
                                }
                            }

                            if ($user['status'] == 1
                                || $user['status'] == 2
                                || $user_stored['status'] == 1
                                || $user_stored['status'] == 2)
                            {
                                delete_thumbprint('active');
                            }

                            if ($user['status'] == 7
                                || $user_stored['status'] == 7)
                            {
                                delete_thumbprint('extern');
                            }

                            $app['intersystems']->clear_cache($app['s_schema']);
                        }

                        $app['link']->redirect('users', $app['pp_ary'], ['id' => $edit]);
                    }
                    else
                    {
                        $app['alert']->error('Gebruiker niet aangepast.');
                    }
                }
            }
            else
            {
                $app['alert']->error($errors);

                if ($edit)
                {
                    $user['adate'] = $user_prefetch['adate'];
                }

                $user['minlimit'] = $user['minlimit'] === -999999999 ? '' : $user['minlimit'];
                $user['maxlimit'] = $user['maxlimit'] === 999999999 ? '' : $user['maxlimit'];
            }
        }
        else
        {
            if ($edit)
            {
                $user = $app['user_cache']->get($edit, $app['tschema']);
                $fullname_access = $user['fullname_access'];
            }

            if ($app['s_admin'])
            {
                $contact = $app['db']->fetchAll('select name, abbrev,
                    \'\' as value, 0 as id
                    from ' . $app['tschema'] . '.type_contact
                    where abbrev in (\'mail\', \'adr\', \'tel\', \'gsm\')');
            }

            if ($edit && $app['s_admin'])
            {
                $contact_keys = [];

                foreach ($contact as $key => $c)
                {
                    $contact_keys[$c['abbrev']] = $key;
                }

                $st = $app['db']->prepare('select tc.abbrev, c.value, tc.name, c.flag_public, c.id
                    from ' . $app['tschema'] . '.type_contact tc, ' .
                        $app['tschema'] . '.contact c
                    where tc.id = c.id_type_contact
                        and c.id_user = ?');

                $st->bindValue(1, $edit);
                $st->execute();

                while ($row = $st->fetch())
                {
                    if (isset($contact_keys[$row['abbrev']]))
                    {
                        $contact[$contact_keys[$row['abbrev']]] = $row;
                        unset($contact_keys[$row['abbrev']]);
                        continue;
                    }

                    $contact[] = $row;
                }
            }
            else if ($app['s_admin'])
            {
                $user = [
                    'minlimit'		=> $app['config']->get('preset_minlimit', $app['tschema']),
                    'maxlimit'		=> $app['config']->get('preset_maxlimit', $app['tschema']),
                    'accountrole'	=> 'user',
                    'status'		=> '1',
                    'cron_saldo'	=> 1,
                ];

                if ($intersystem_code)
                {
                    if ($group = $app['db']->fetchAssoc('select *
                        from ' . $app['tschema'] . '.letsgroups
                        where localletscode = ?
                            and apimethod <> \'internal\'', [$intersystem_code]))
                    {
                        $user['name'] = $user['fullname'] = $group['groupname'];

                        if ($group['url']
                            && ($app['systems']->get_schema_from_legacy_eland_origin($group['url'])))
                        {
                            $remote_schema = $app['systems']->get_schema_from_legacy_eland_origin($group['url']);

                            $admin_mail = $app['config']->get('admin', $remote_schema);

                            foreach ($contact as $k => $c)
                            {
                                if ($c['abbrev'] == 'mail')
                                {
                                    $contact[$k]['value'] = $admin_mail;
                                    break;
                                }
                            }

                            // name from source is preferable
                            $user['name'] = $user['fullname'] = $app['config']->get('systemname', $remote_schema);
                        }
                    }

                    $user['cron_saldo'] = 0;
                    $user['status'] = '7';
                    $user['accountrole'] = 'interlets';
                    $user['letscode'] = $intersystem_code;
                }
                else
                {
                    $user['cron_saldo'] = 1;
                    $user['status'] = '1';
                    $user['accountrole'] = 'user';
                }
            }
        }

        if ($edit)
        {
            $edit_user_cached = $app['user_cache']->get($edit, $app['tschema']);
        }

        array_walk($user, function(&$value, $key){ $value = trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')); });
        array_walk($contact, function(&$value, $key){ $value['value'] = trim(htmlspecialchars($value['value'], ENT_QUOTES, 'UTF-8')); });

        $app['assets']->add([
            'datepicker',
            'generate_password.js',
            'generate_password_onload.js',
            'user_edit.js',
        ]);

        if ($s_owner && !$app['s_admin'] && $edit)
        {
            $app['heading']->add('Je profiel aanpassen');
        }
        else
        {
            $app['heading']->add('Gebruiker ');

            if ($edit)
            {
                $app['heading']->add('aanpassen: ');
                $app['heading']->add($app['account']->link($edit, $app['pp_ary']));
            }
            else
            {
                $app['heading']->add('toevoegen');
            }
        }

        $app['heading']->fa('user');

        include __DIR__ . '/include/header.php';

        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading">';

        echo '<form method="post">';

        if ($app['s_admin'])
        {
            echo '<div class="form-group">';
            echo '<label for="letscode" class="control-label">';
            echo 'Account Code';
            echo '</label>';
            echo '<div class="input-group">';
            echo '<span class="input-group-addon">';
            echo '<span class="fa fa-user"></span></span>';
            echo '<input type="text" class="form-control" ';
            echo 'id="letscode" name="letscode" ';
            echo 'value="';
            echo $user['letscode'] ?? '';
            echo '" required maxlength="20" ';
            echo 'data-typeahead="';

            echo $app['typeahead']->ini($app['pp_ary'])
                ->add('account_codes', [])
                ->str([
                    'render'	=> [
                        'check'	=> 10,
                        'omit'	=> $edit_user_cached['letscode'] ?? '',
                    ]
                ]);

            echo '">';
            echo '</div>';
            echo '<span class="help-block hidden exists_query_results">';
            echo 'Reeds gebruikt: ';
            echo '<span class="query_results">';
            echo '</span>';
            echo '</span>';
            echo '<span class="help-block hidden exists_msg">';
            echo 'Deze Account Code bestaat al!';
            echo '</span>';
            echo '</div>';
        }

        if ($username_edit)
        {
            echo '<div class="form-group">';
            echo '<label for="name" class="control-label">';
            echo 'Gebruikersnaam</label>';
            echo '<div class="input-group">';
            echo '<span class="input-group-addon">';
            echo '<span class="fa fa-user"></span></span>';
            echo '<input type="text" class="form-control" ';
            echo 'id="name" name="name" ';
            echo 'value="';
            echo $user['name'] ?? '';
            echo '" required maxlength="50" ';
            echo 'data-typeahead="';

            echo $app['typeahead']->ini($app['pp_ary'])
                ->add('usernames', [])
                ->str([
                    'render'	=> [
                        'check'	=> 10,
                        'omit'	=> $edit_user_cached['name'] ?? '',
                    ]
                ]);

            echo '">';
            echo '</div>';
            echo '<span class="help-block hidden exists_query_results">';
            echo 'Reeds gebruikt: ';
            echo '<span class="query_results">';
            echo '</span>';
            echo '</span>';
            echo '<span id="username_exists" ';
            echo 'class="help-block hidden exists_msg">';
            echo 'Deze Gebruikersnaam bestaat reeds!</span>';
            echo '</div>';
        }

        if ($fullname_edit)
        {
            echo '<div class="form-group">';
            echo '<label for="fullname" class="control-label">';
            echo 'Volledige Naam</label>';
            echo '<div class="input-group">';
            echo '<span class="input-group-addon">';
            echo '<span class="fa fa-user"></span></span>';
            echo '<input type="text" class="form-control" ';
            echo 'id="fullname" name="fullname" ';
            echo 'value="';
            echo $user['fullname'] ?? '';
            echo '" maxlength="100">';
            echo '</div>';
            echo '<p>';
            echo 'Voornaam en Achternaam';
            echo '</p>';
            echo '</div>';
        }

        if (!isset($fullname_access))
        {
            $fullname_access = $add && !$intersystem_code ? '' : 'admin';
        }

        echo $app['item_access']->get_radio_buttons(
            'users_fullname',
            $fullname_access,
            'fullname_access',
            false,
            'Zichtbaarheid Volledige Naam'
        );

        echo '<div class="form-group">';
        echo '<label for="postcode" class="control-label">';
        echo 'Postcode</label>';
        echo '<div class="input-group">';
        echo '<span class="input-group-addon">';
        echo '<span class="fa fa-map-marker"></span></span>';
        echo '<input type="text" class="form-control" ';
        echo 'id="postcode" name="postcode" ';
        echo 'value="';
        echo $user['postcode'] ?? '';
        echo '" ';
        echo 'required maxlength="6" ';
        echo 'data-typeahead="';

        echo $app['typeahead']->ini($app['pp_ary'])
            ->add('postcodes', [])
            ->str();

        echo '">';
        echo '</div>';
        echo '</div>';

        echo '<div class="form-group">';
        echo '<label for="birthday" class="control-label">';
        echo 'Geboortedatum</label>';
        echo '<div class="input-group">';
        echo '<span class="input-group-addon">';
        echo '<span class="fa fa-calendar"></span></span>';
        echo '<input type="text" class="form-control" ';
        echo 'id="birthday" name="birthday" ';
        echo 'value="';

        if (isset($user['birthday']) && !empty($user['birtday']))
        {
            echo $app['date_format']->get($user['birthday'], 'day', $app['tschema']);
        }

        echo '" ';
        echo 'data-provide="datepicker" ';
        echo 'data-date-format="';
        echo $app['date_format']->datepicker_format($app['tschema']);
        echo '" ';
        echo 'data-date-default-view="2" ';
        echo 'data-date-end-date="';
        echo $app['date_format']->get('', 'day', $app['tschema']);
        echo '" ';
        echo 'data-date-language="nl" ';
        echo 'data-date-start-view="2" ';
        echo 'data-date-today-highlight="true" ';
        echo 'data-date-autoclose="true" ';
        echo 'data-date-immediate-updates="true" ';
        echo 'data-date-orientation="bottom" ';
        echo 'placeholder="';
        echo $app['date_format']->datepicker_placeholder($app['tschema']);
        echo '">';
        echo '</div>';
        echo '</div>';

        echo '<div class="form-group">';
        echo '<label for="hobbies" class="control-label">';
        echo 'Hobbies, interesses</label>';
        echo '<textarea name="hobbies" id="hobbies" ';
        echo 'class="form-control" maxlength="500">';
        echo $user['hobbies'] ?? '';
        echo '</textarea>';
        echo '</div>';

        echo '<div class="form-group">';
        echo '<label for="comments" class="control-label">Commentaar</label>';
        echo '<div class="input-group">';
        echo '<span class="input-group-addon">';
        echo '<span class="fa fa-comment-o"></span></span>';
        echo '<input type="text" class="form-control" ';
        echo 'id="comments" name="comments" ';
        echo 'value="';
        echo $user['comments'] ?? '';
        echo '">';
        echo '</div>';
        echo '</div>';

        if ($app['s_admin'])
        {
            echo '<div class="form-group">';
            echo '<label for="accountrole" class="control-label">';
            echo 'Rechten / Rol</label>';
            echo '<div class="input-group">';
            echo '<span class="input-group-addon">';
            echo '<span class="fa fa-hand-paper-o"></span></span>';
            echo '<select id="accountrole" name="accountrole" ';
            echo 'class="form-control">';
            echo $app['select']->get_options(cnst_role::LABEL_ARY, $user['accountrole']);
            echo '</select>';
            echo '</div>';
            echo '</div>';

            echo '<div class="pan-sub" id="presharedkey_panel">';
            echo '<div class="form-group" id="presharedkey_formgroup">';
            echo '<label for="presharedkey" class="control-label">';
            echo 'Preshared Key</label>';
            echo '<div class="input-group">';
            echo '<span class="input-group-addon">';
            echo '<span class="fa fa-key"></span></span>';
            echo '<input type="text" class="form-control" ';
            echo 'id="presharedkey" name="presharedkey" ';
            echo 'value="';
            echo $user['presharedkey'] ?? '';
            echo '" maxlength="80">';
            echo '</div>';
            echo '<p>Vul dit enkel in voor een interSysteem Account ';
            echo 'van een Systeem op een eLAS-server.</p>';
            echo '</div>';
            echo '</div>';

            echo '<div class="form-group">';
            echo '<label for="status" class="control-label">';
            echo 'Status</label>';
            echo '<div class="input-group">';
            echo '<span class="input-group-addon">';
            echo '<span class="fa fa-star-o"></span></span>';
            echo '<select id="status" name="status" class="form-control">';
            echo $app['select']->get_options(cnst_status::LABEL_ARY, $user['status']);
            echo '</select>';
            echo '</div>';
            echo '</div>';

            if (empty($user['adate']) && $app['s_admin'])
            {
                echo '<div id="activate" class="bg-success pan-sub">';

                echo '<div class="form-group">';
                echo '<label for="password" class="control-label">';
                echo 'Paswoord</label>';
                echo '<div class="input-group">';
                echo '<span class="input-group-addon">';
                echo '<span class="fa fa-key"></span></span>';
                echo '<input type="text" class="form-control" ';
                echo 'id="password" name="password" ';
                echo 'value="';
                echo $password ?? '';
                echo '" required>';
                echo '<span class="input-group-btn">';
                echo '<button class="btn btn-default" ';
                echo 'type="button" id="generate">';
                echo 'Genereer</button>';
                echo '</span>';
                echo '</div>';
                echo '</div>';

                echo '<div class="form-group">';
                echo '<label for="notify" class="control-label">';
                echo '<input type="checkbox" name="notify" id="notify"';
                echo ' checked="checked"';
                echo '> ';
                echo 'Verstuur een E-mail met het ';
                echo 'paswoord naar de gebruiker. ';
                echo 'Dit kan enkel wanneer het account ';
                echo 'de status actief heeft en ';
                echo 'een E-mail adres is ingesteld.';
                echo '</label>';
                echo '</div>';

                echo '</div>';
            }

            echo '<div class="form-group">';
            echo '<label for="admincomment" class="control-label">';
            echo 'Commentaar van de admin</label>';
            echo '<textarea name="admincomment" id="admincomment" ';
            echo 'class="form-control" maxlength="200">';
            echo $user['admincomment'] ?? '';
            echo '</textarea>';
            echo '</div>';

            echo '<div class="pan-sub">';

            echo '<h2>Limieten&nbsp;';

            if ($user['minlimit'] === '' && $user['maxlimit'] === '')
            {
                echo '<button class="btn btn-default" ';
                echo 'title="Limieten instellen" data-toggle="collapse" ';
                echo 'data-target="#limits_pan" type="button">';
                echo 'Instellen</button>';
            }

            echo '</h2>';

            echo '<div id="limits_pan"';

            if ($user['minlimit'] === '' && $user['maxlimit'] === '')
            {
                echo ' class="collapse"';
            }

            echo '>';

            echo '<div class="form-group">';
            echo '<label for="minlimit" class="control-label">';
            echo 'Minimum Account Limiet</label>';
            echo '<div class="input-group">';
            echo '<span class="input-group-addon">';
            echo '<span class="fa fa-arrow-down"></span> ';
            echo $app['config']->get('currency', $app['tschema']);
            echo '</span>';
            echo '<input type="number" class="form-control" ';
            echo 'id="minlimit" name="minlimit" ';
            echo 'value="';
            echo $user['minlimit'] ?? '';
            echo '">';
            echo '</div>';
            echo '<p>Vul enkel in wanneer je een individueel ';
            echo 'afwijkende minimum limiet wil instellen ';
            echo 'voor dit account. Als dit veld leeg is, ';
            echo 'dan is de algemeen geldende ';
            echo $app['link']->link_no_attr('config', $app['pp_ary'],
                ['tab' => 'balance'], 'Minimum Systeemslimiet');
            echo ' ';
            echo 'van toepassing. ';

            if ($app['config']->get('minlimit', $app['tschema']) === '')
            {
                echo 'Er is momenteel <strong>geen</strong> algemeen ';
                echo 'geledende Minimum Systeemslimiet ingesteld. ';
            }
            else
            {
                echo 'De algemeen geldende ';
                echo 'Minimum Systeemslimiet bedraagt <strong>';
                echo $app['config']->get('minlimit', $app['tschema']);
                echo ' ';
                echo $app['config']->get('currency', $app['tschema']);
                echo '</strong>. ';
            }

            echo 'Dit veld wordt bij aanmaak van een ';
            echo 'gebruiker vooraf ingevuld met de "';
            echo $app['link']->link_no_attr('config', $app['pp_ary'],
                ['tab' => 'balance'],
                'Preset Individuele Minimum Account Limiet');
            echo '" ';
            echo 'die gedefiniëerd is in de instellingen.';

            if ($app['config']->get('preset_minlimit', $app['tschema']) !== '')
            {
                echo ' De Preset bedraagt momenteel <strong>';
                echo $app['config']->get('preset_minlimit', $app['tschema']);
                echo '</strong>.';
            }

            echo '</p>';
            echo '</div>';

            echo '<div class="form-group">';
            echo '<label for="maxlimit" class="control-label">';
            echo 'Maximum Account Limiet</label>';
            echo '<div class="input-group">';
            echo '<span class="input-group-addon">';
            echo '<span class="fa fa-arrow-up"></span> ';
            echo $app['config']->get('currency', $app['tschema']);
            echo '</span>';
            echo '<input type="number" class="form-control" ';
            echo 'id="maxlimit" name="maxlimit" ';
            echo 'value="';
            echo $user['maxlimit'] ?? '';
            echo '">';
            echo '</div>';

            echo '<p>Vul enkel in wanneer je een individueel ';
            echo 'afwijkende maximum limiet wil instellen ';
            echo 'voor dit account. Als dit veld leeg is, ';
            echo 'dan is de algemeen geldende ';
            echo $app['link']->link_no_attr('config', $app['pp_ary'],
                ['tab' => 'balance'],
                'Maximum Systeemslimiet');
            echo ' ';
            echo 'van toepassing. ';

            if ($app['config']->get('maxlimit', $app['tschema']) === '')
            {
                echo 'Er is momenteel <strong>geen</strong> algemeen ';
                echo 'geledende Maximum Systeemslimiet ingesteld. ';
            }
            else
            {
                echo 'De algemeen geldende Maximum ';
                echo 'Systeemslimiet bedraagt <strong>';
                echo $app['config']->get('maxlimit', $app['tschema']);
                echo ' ';
                echo $app['config']->get('currency', $app['tschema']);
                echo '</strong>. ';
            }

            echo 'Dit veld wordt bij aanmaak van een gebruiker ';
            echo 'vooraf ingevuld wanneer "';
            echo $app['link']->link_no_attr('config', $app['pp_ary'],
                ['tab' => 'balance'],
                'Preset Individuele Maximum Account Limiet');
            echo '" ';
            echo 'is ingevuld in de instellingen.';

            if ($app['config']->get('preset_maxlimit', $app['tschema']) !== '')
            {
                echo ' De Preset bedraagt momenteel <strong>';
                echo $app['config']->get('preset_maxlimit', $app['tschema']);
                echo '</strong>.';
            }

            echo '</p>';

            echo '</div>';
            echo '</div>';
            echo '</div>';

            $contacts_format = [
                'adr'	=> [
                    'fa'		=> 'map-marker',
                    'lbl'		=> 'Adres',
                    'explain'	=> 'Voorbeeldstraat 23, 4520 Voorbeeldgemeente',
                ],
                'gsm'	=> [
                    'fa'		=> 'mobile',
                    'lbl'		=> 'GSM',
                ],
                'tel'	=> [
                    'fa'		=> 'phone',
                    'lbl'		=> 'Telefoon',
                ],
                'mail'	=> [
                    'fa'		=> 'envelope-o',
                    'lbl'		=> 'E-mail',
                    'type'		=> 'email',
                    'disabled'	=> true,     // Prevent browser fill-in, removed by js.
                ],
                'web'	=> [
                    'fa'		=> 'link',
                    'lbl'		=> 'Website',
                    'type'		=> 'url',
                ],
            ];

            echo '<div class="bg-warning pan-sub">';
            echo '<h2><i class="fa fa-map-marker"></i> Contacten</h2>';

            echo '<p>Meer contacten kunnen toegevoegd worden ';
            echo 'vanuit de profielpagina met de knop ';
            echo 'Toevoegen bij de contactinfo ';
            echo $add ? 'nadat de gebruiker gecreëerd is' : '';
            echo '.</p>';

            foreach ($contact as $key => $c)
            {
                $name = 'contact[' . $key . '][value]';

                echo '<div class="pan-sab">';

                echo '<div class="form-group">';
                echo '<label for="';
                echo $name;
                echo '" class="control-label">';
                echo $contacts_format[$c['abbrev']]['lbl'] ?? $c['abbrev'];
                echo '</label>';
                echo '<div class="input-group">';
                echo '<span class="input-group-addon">';
                echo '<i class="fa fa-';
                echo $contacts_format[$c['abbrev']]['fa'] ?? 'question-mark';
                echo '"></i>';
                echo '</span>';
                echo '<input class="form-control" id="';
                echo $name;
                echo '" name="';
                echo $name;
                echo '" ';
                echo 'value="';
                echo $c['value'] ?? '';
                echo '" type="';
                echo $contacts_format[$c['abbrev']]['type'] ?? 'text';
                echo '" ';
                echo isset($contacts_format[$c['abbrev']]['disabled']) ? 'disabled ' : '';
                echo 'data-access="contact_access_' . $key . '">';
                echo '</div>';
                echo '<p>';
                echo $contacts_format[$c['abbrev']]['explain'] ?? '';
                echo '</p>';
                echo '</div>';

                echo $app['item_access']->get_radio_buttons(
                    $c['abbrev'],
                    $app['item_access']->get_value_from_flag_public($c['flag_public']),
                    'contact_access_' . $key
                );

                echo '<input type="hidden" ';
                echo 'name="contact['. $key . '][id]" value="' . $c['id'] . '">';
                echo '<input type="hidden" ';
                echo 'name="contact['. $key . '][name]" value="' . $c['name'] . '">';
                echo '<input type="hidden" ';
                echo 'name="contact['. $key . '][abbrev]" value="' . $c['abbrev'] . '">';

                echo '</div>';
            }

            echo '</div>';
        }

        echo '<div class="form-group">';
        echo '<label for="cron_saldo" class="control-label">';
        echo '<input type="checkbox" name="cron_saldo" id="cron_saldo"';
        echo $user['cron_saldo'] ? ' checked="checked"' : '';
        echo '>	';
        echo 'Periodieke Overzichts E-mail';
        echo '</label>';
        echo '</div>';

        $btn = $edit ? 'primary' : 'success';

        echo $app['link']->btn_cancel('users', $app['pp_ary'],
            $edit ? ['id' => $edit] : ['status' => 'active']);

        echo '&nbsp;';
        echo '<input type="submit" name="zend" ';
        echo 'value="Opslaan" class="btn btn-';
        echo $btn . '">';
        echo $app['form_token']->get_hidden_input();

        echo '</form>';

        echo '</div>';
        echo '</div>';

    }
}
