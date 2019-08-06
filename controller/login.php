<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use cnst\role as cnst_role;

class login
{
    public function form(Request $request, app $app):Response
    {
        $location = $_GET['location'] ?? false;

        if (!$location
            || strpos($location, 'login') !== false
            || strpos($location, 'logout') !== false
            || $location == ''
            || $location == '/')
        {
            $location = $app['config']->get('default_landing_page', $app['tschema']);
        }

        $login = trim($request->request->get('login', ''));

        if ($request->isMethod('POST'))
        {
            $lc_login = strtolower($login);
            $password = trim($request->request->get('password'));

            $errors = [];

            if (!($lc_login && $password))
            {
                $errors[] = 'Login gefaald. Vul Login en Paswoord in.';
            }

            $master_password = getenv('MASTER_PASSWORD');

            if ($lc_login === 'master'
                && $master_password
                && hash('sha512', $password) === $master_password)
            {
                $app['s_logins'] = array_merge($app['s_logins'], [
                    $app['tschema'] 	=> 'master',
                ]);
                $app['session']->set('logins', $app['s_logins']);
                $app['session']->set('schema', $app['tschema']);

                $app['alert']->success('OK - Gebruiker ingelogd als master.');

                $query = [];
                $route = $location;

                if (strpos($location, '?') !== false)
                {
                    [$route, $query_str] = explode('?', $location);
                    parse_str($query_str, $query);
                }

                $pp_ary = [
                    'system'        => $app['pp_system'],
                    'role_short'    => 'a',
                ];

                $app['link']->redirect($route, $pp_ary, $query);
            }

            $user_id = false;

            if (!count($errors) && filter_var($lc_login, FILTER_VALIDATE_EMAIL))
            {
                $count_email = $app['db']->fetchColumn('select count(c.*)
                    from ' . $app['tschema'] . '.contact c, ' .
                        $app['tschema'] . '.type_contact tc, ' .
                        $app['tschema'] . '.users u
                    where c.id_type_contact = tc.id
                        and tc.abbrev = \'mail\'
                        and c.id_user = u.id
                        and u.status in (1, 2)
                        and lower(c.value) = ?', [$lc_login]);

                if ($count_email == 1)
                {
                    $user_id = $app['db']->fetchColumn('select u.id
                        from ' . $app['tschema'] . '.contact c, ' .
                            $app['tschema'] . '.type_contact tc, ' .
                            $app['tschema'] . '.users u
                        where c.id_type_contact = tc.id
                            and tc.abbrev = \'mail\'
                            and c.id_user = u.id
                            and u.status in (1, 2)
                            and lower(c.value) = ?', [$lc_login]);
                }
                else
                {
                    $err = 'Je kan dit E-mail adres niet gebruiken ';
                    $err .= 'om in te loggen want het is niet ';
                    $err .= 'uniek aanwezig in dit Systeem. Gebruik ';
                    $err .= 'je Account Code of Gebruikersnaam.';
                    $errors[] = $err;
                }
            }

            if (!$user_id && !count($errors))
            {
                $count_letscode = $app['db']->fetchColumn('select count(u.*)
                    from ' . $app['tschema'] . '.users u
                    where lower(letscode) = ?', [$lc_login]);

                if ($count_letscode > 1)
                {
                    $err = 'Je kan deze Account Code niet gebruiken ';
                    $err .= 'om in te loggen want deze is niet ';
                    $err .= 'uniek aanwezig in dit Systeem. Gebruik ';
                    $err .= 'je E-mail adres of gebruikersnaam.';
                    $errors[] = $err;
                }
                else if ($count_letscode == 1)
                {
                    $user_id = $app['db']->fetchColumn('select id
                        from ' . $app['tschema'] . '.users
                        where lower(letscode) = ?', [$lc_login]);
                }
            }

            if (!$user_id && !count($errors))
            {
                $count_name = $app['db']->fetchColumn('select count(u.*)
                    from ' . $app['tschema'] . '.users u
                    where lower(name) = ?', [$lc_login]);

                if ($count_name > 1)
                {
                    $err = 'Je kan deze gebruikersnaam niet gebruiken ';
                    $err .= 'om in te loggen want deze is niet ';
                    $err .= 'uniek aanwezig in dit Systeem. Gebruik ';
                    $err .= 'je E-mail adres of Account Code.';
                    $errors[] = $err;
                }
                else if ($count_name == 1)
                {
                    $user_id = $app['db']->fetchColumn('select id
                        from ' . $app['tschema'] . '.users
                        where lower(name) = ?', [$lc_login]);
                }
            }

            if (!$user_id && !count($errors))
            {
                $errors[] = 'Login gefaald. Onbekende gebruiker.';
            }
            else if ($user_id && !count($errors))
            {
                $user = $app['user_cache']->get($user_id, $app['tschema']);

                if (!$user)
                {
                    $errors[] = 'Onbekende gebruiker.';
                }
                else
                {
                    $log_ary = [
                        'user_id'	=> $user['id'],
                        'letscode'	=> $user['letscode'],
                        'username'	=> $user['name'],
                        'schema' 	=> $app['tschema'],
                    ];

                    $sha512 = hash('sha512', $password);
                    $sha1 = sha1($password);
                    $md5 = md5($password);

                    if (!in_array($user['password'], [$sha512, $sha1, $md5]))
                    {
                        $errors[] = 'Het paswoord is niet correct.';
                    }
                    else if ($user['password'] !== $sha512)
                    {
                        $app['db']->update($app['tschema'] . '.users',
                            ['password' => hash('sha512', $password)],
                            ['id' => $user_id]);

                        $app['monolog']->info('Password encryption updated to sha512', $log_ary);
                    }
                }
            }

            if (!count($errors) && !in_array($user['status'], [1, 2]))
            {
                $errors[] = 'Het account is niet actief.';
            }

            if (!count($errors) && !in_array($user['accountrole'], ['user', 'admin']))
            {
                $errors[] = 'Het account beschikt niet over de juiste rechten.';
            }

            if (!count($errors)
                && $app['config']->get('maintenance', $app['tschema'])
                && $user['accountrole'] != 'admin')
            {
                $errors[] = 'De website is in onderhoud, probeer later opnieuw';
            }

            if (!count($errors))
            {
                $s_logins = array_merge($app['s_logins'], [
                    $app['tschema'] 	=> $user_id,
                ]);

                $app['session']->set('logins', $s_logins);
                $app['session']->set('schema', $app['tschema']);

                $agent = $request->server->get('HTTP_USER_AGENT');

                $app['monolog']->info('User ' .
                    $app['account']->str_id($user_id, $app['tschema']) .
                    ' logged in, agent: ' . $agent, $log_ary);

                $app['db']->update($app['tschema'] . '.users',
                    ['lastlogin' => gmdate('Y-m-d H:i:s')],
                    ['id' => $user_id]);

                $app['user_cache']->clear($user_id, $app['tschema']);

                $app['xdb']->set('login', (string) $user_id, [
                    'browser' => $agent, 'time' => time()
                ], $app['s_schema']);

                $app['alert']->success('Je bent ingelogd.');

                $query = [];
                $route = $location;

                if (strpos($location, '?') !== false)
                {
                    [$route, $query_str] = explode('?', $location);
                    parse_str($query_str, $query);
                }

                $pp_ary = [
                    'system'		=> $app['pp_system'],
                    'role_short'	=> cnst_role::SHORT[$user['accountrole']],
                ];

                $app['link']->redirect($location, $pp_ary, $query);
            }

            $app['alert']->error($errors);
        }

        if($app['config']->get('maintenance', $app['tschema']))
        {
            $app['alert']->warning('De website is niet beschikbaar
                wegens onderhoudswerken.  Enkel admins kunnen inloggen');
        }

        $app['heading']->add('Login');
        $app['heading']->fa('sign-in');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="login">';
        $out .= 'Login</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="login" name="login" ';
        $out .= 'value="';
        $out .= $login;
        $out .= '" required>';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'E-mail, Account Code of Gebruikersnaam';
        $out .= '</p>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="password">Paswoord</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-key"></i>';
        $out .= '</span>';
        $out .= '<input type="password" class="form-control" ';
        $out .= 'id="password" name="password" ';
        $out .= 'value="" required>';
        $out .= '</div>';
        $out .= '<p>';
        $out .= $app['link']->link_no_attr('password_reset',
            $app['pp_ary'], [],
            'Klik hier als je je paswoord vergeten bent.');
        $out .= '</p>';
        $out .= '</div>';

        $out .= '<input type="submit" class="btn btn-default" ';
        $out .= 'value="Inloggen" name="zend">';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);

        return $app['tpl']->get($request);
    }
}
