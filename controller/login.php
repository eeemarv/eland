<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use cnst\role as cnst_role;

class login
{
    public function form(app $app):Response
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

        if ($app['request']->isMethod('POST'))
        {
            $login = trim(strtolower($app['request']->request->get('login')));
            $password = trim($app['request']->request->get('password'));

            if (!($login && $password))
            {
                $errors[] = 'Login gefaald. Vul Login en Paswoord in.';
            }

            $master_password = getenv('MASTER_PASSWORD');

            if ($login == 'master'
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

            if (!count($errors) && filter_var($login, FILTER_VALIDATE_EMAIL))
            {
                $count_email = $app['db']->fetchColumn('select count(c.*)
                    from ' . $app['tschema'] . '.contact c, ' .
                        $app['tschema'] . '.type_contact tc, ' .
                        $app['tschema'] . '.users u
                    where c.id_type_contact = tc.id
                        and tc.abbrev = \'mail\'
                        and c.id_user = u.id
                        and u.status in (1, 2)
                        and lower(c.value) = ?', [$login]);

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
                            and lower(c.value) = ?', [$login]);
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
                    where lower(letscode) = ?', [$login]);

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
                        where lower(letscode) = ?', [$login]);
                }
            }

            if (!$user_id && !count($errors))
            {
                $count_name = $app['db']->fetchColumn('select count(u.*)
                    from ' . $app['tschema'] . '.users u
                    where lower(name) = ?', [$login]);

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
                        where lower(name) = ?', [$login]);
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

                $agent = $app['request']->server->get('HTTP_USER_AGENT');

                $app['monolog']->info('User ' .
                    $app['account']->str_id($user_id, $app['tschema']) .
                    ' logged in, agent: ' . $agent, $log_ary);

                $app['db']->update($app['tschema'] . '.users',
                    ['lastlogin' => gmdate('Y-m-d H:i:s')],
                    ['id' => $user_id]);

                $app['user_cache']->clear($user_id, $app['tschema']);

                $app['xdb']->set('login', $user_id, [
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

        ob_start();

        require_once __DIR__ . '/../include/header.php';

        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading">';

        echo '<form method="post">';

        echo '<div class="form-group">';
        echo '<label for="login">';
        echo 'Login</label>';
        echo '<div class="input-group">';
        echo '<span class="input-group-addon">';
        echo '<i class="fa fa-user"></i>';
        echo '</span>';
        echo '<input type="text" class="form-control" id="login" name="login" ';
        echo 'value="';
        echo $login;
        echo '" required>';
        echo '</div>';
        echo '<p>';
        echo 'E-mail, Account Code of Gebruikersnaam';
        echo '</p>';
        echo '</div>';

        echo '<div class="form-group">';
        echo '<label for="password">Paswoord</label>';
        echo '<div class="input-group">';
        echo '<span class="input-group-addon">';
        echo '<i class="fa fa-key"></i>';
        echo '</span>';
        echo '<input type="password" class="form-control" ';
        echo 'id="password" name="password" ';
        echo 'value="" required>';
        echo '</div>';
        echo '<p>';
        echo $app['link']->link_no_attr('password_reset',
            $app['pp_ary'], [],
            'Klik hier als je je paswoord vergeten bent.');
        echo '</p>';
        echo '</div>';

        echo '<input type="submit" class="btn btn-default" ';
        echo 'value="Inloggen" name="zend">';

        echo '</form>';

        echo '</div>';
        echo '</div>';

        include __DIR__ . '/../include/footer.php';

        return new Response(ob_get_clean());
    }
}
