<?php declare(strict_types=1);

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Security\User;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

class LoginController extends AbstractController
{
    #[Route(
        '/{system}/login',
        name: 'login',
        methods: ['GET', 'POST'],
        priority: 30,
        requirements: [
            'system'        => '%assert.system%',
        ],
    )]

    public function __invoke(
        Request $request,
        Db $db,
        EncoderFactoryInterface $encoder_factory,
        AlertService $alert_service,
        LoggerInterface $logger,
        MenuService $menu_service,
        LinkRender $link_render,
        ConfigService $config_service,
        AccountRender $account_render,
        UserCacheService $user_cache_service,
        FormTokenService $form_token_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        string $env_master_password
    ):Response
    {
        $errors = [];

        $location = $request->query->get('location', '');

        if (!$location
            || str_contains($location, 'login')
            || str_contains($location, 'logout')
            || $location === '/')
        {
            $location = '';
        }

        if ($request->isMethod('POST'))
        {
            $login = trim($request->request->get('login', ''));
            $lowercase_login = strtolower($login);
            $password = trim($request->request->get('password'));

            $encoder = $encoder_factory->getEncoder(new User());

            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if (!($lowercase_login && $password))
            {
                $errors[] = 'Login gefaald. Vul Login en Paswoord in.';
            }

            if ($lowercase_login === 'master'
                && $env_master_password
                && $encoder->isPasswordValid($env_master_password, $password, null))
            {
                $su->set_master_login($pp->schema());

                $alert_service->success('OK - Gebruiker ingelogd als master.');

                if ($location)
                {
                    header('Location: ' . $location);
                    exit;
                }

                $pp_ary = [
                    'system'        => $pp->system(),
                    'role_short'    => 'a',
                ];

                $link_render->redirect($vr->get('default'), $pp_ary, []);
            }

            $user_id = false;

            if (!count($errors) && filter_var($lowercase_login, FILTER_VALIDATE_EMAIL))
            {
                $count_email = $db->fetchOne('select count(c.*)
                    from ' . $pp->schema() . '.contact c, ' .
                        $pp->schema() . '.type_contact tc, ' .
                        $pp->schema() . '.users u
                    where c.id_type_contact = tc.id
                        and tc.abbrev = \'mail\'
                        and c.user_id = u.id
                        and u.status in (1, 2)
                        and lower(c.value) = ?',
                        [$lowercase_login],
                        [\PDO::PARAM_STR]
                    );

                if ($count_email == 1)
                {
                    $user_id = $db->fetchOne('select u.id
                        from ' . $pp->schema() . '.contact c, ' .
                            $pp->schema() . '.type_contact tc, ' .
                            $pp->schema() . '.users u
                        where c.id_type_contact = tc.id
                            and tc.abbrev = \'mail\'
                            and c.user_id = u.id
                            and u.status in (1, 2)
                            and lower(c.value) = ?',
                            [$lowercase_login],
                            [\PDO::PARAM_STR]
                        );
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
                $count_code = $db->fetchOne('select count(u.*)
                    from ' . $pp->schema() . '.users u
                    where lower(code) = ?',
                    [$lowercase_login],
                    [\PDO::PARAM_STR]
                );

                if ($count_code > 1)
                {
                    $err = 'Je kan deze Account Code niet gebruiken ';
                    $err .= 'om in te loggen want deze is niet ';
                    $err .= 'uniek aanwezig in dit Systeem. Gebruik ';
                    $err .= 'je E-mail adres of gebruikersnaam.';
                    $errors[] = $err;
                }
                else if ($count_code == 1)
                {
                    $user_id = $db->fetchOne('select id
                        from ' . $pp->schema() . '.users
                        where lower(code) = ?',
                        [$lowercase_login],
                        [\PDO::PARAM_STR]
                    );
                }
            }

            if (!$user_id && !count($errors))
            {
                $count_name = $db->fetchOne('select count(u.*)
                    from ' . $pp->schema() . '.users u
                    where lower(name) = ?',
                    [$lowercase_login],
                    [\PDO::PARAM_STR]
                );

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
                    $user_id = $db->fetchOne('select id
                        from ' . $pp->schema() . '.users
                        where lower(name) = ?',
                        [$lowercase_login],
                        [\PDO::PARAM_STR]
                    );
                }
            }

            if (!$user_id && !count($errors))
            {
                $errors[] = 'Login gefaald. Onbekende gebruiker.';
            }
            else if ($user_id && !count($errors))
            {
                $user = $user_cache_service->get($user_id, $pp->schema());

                if (!$user)
                {
                    $errors[] = 'Onbekende gebruiker.';
                }
                else
                {
                    $log_ary = [
                        'user_id'	=> $user['id'],
                        'code'	    => $user['code'],
                        'username'	=> $user['name'],
                        'schema' 	=> $pp->schema(),
                    ];

                    if ($user['password'] === hash('sha512', $password))
                    {
                        $hashed_password = $encoder->encodePassword($password, null);

                        $db->update($pp->schema() . '.users',
                            ['password' => $hashed_password],
                            ['id' => $user_id]);

                        $logger->info('Password hashing updated', $log_ary);
                        error_log('Password hashing updated');
                    }
                    else if (!$encoder->isPasswordValid($user['password'], $password, null))
                    {
                        $errors[] = 'Het paswoord is niet correct.';
                    }
                }
            }

            if (!count($errors) && !in_array($user['status'], [1, 2]))
            {
                $errors[] = 'Het account is niet actief.';
            }

            if (!count($errors)
                && !in_array($user['role'], ['user', 'admin'])
                && !$config_service->get_intersystem_en($pp->schema()))
            {
                $errors[] = 'Het account is een interSysteem-gast account,
                    maar interSysteem functionaliteit is niet ingeschakeld in dit systeem.';
            }

            if (!count($errors)
                && $config_service->get_bool('system.maintenance_en', $pp->schema())
                && $user['role'] !== 'admin')
            {
                $errors[] = 'De website is in onderhoud, probeer later opnieuw';
            }

            if (!count($errors))
            {
                $su->set_login($pp->schema(), $user_id);

                $agent = $request->server->get('HTTP_USER_AGENT');

                $logger->info('User ' .
                    $account_render->str_id($user_id, $pp->schema()) .
                    ' logged in, agent: ' . $agent, $log_ary);

                $db->insert($pp->schema() . '.login', [
                    'user_id'       => $user_id,
                    'agent'         => $agent,
                    'ip'            => $request->getClientIp(),
                ]);

                $alert_service->success('Je bent ingelogd.');

                if ($location)
                {
                    header('Location: ' . $location);
                    exit;
                }

                $link_render->redirect($vr->get('default'), $su->ary(), []);
            }

            $alert_service->error($errors);
        }

        if($config_service->get_bool('system.maintenance_en', $pp->schema()))
        {
            $alert_service->warning('De website is niet beschikbaar
                wegens onderhoudswerken.  Enkel admins kunnen inloggen');
        }

        if ($request->isMethod('GET'))
        {
            if ($su->is_user())
            {
                if ($location)
                {
                    if (stripos($location, $pp->system() . '/a/') === false)
                    {
                        header('Location: ' . $location);
                        exit;
                    }
                }

                $link_render->redirect($vr->get('default'), $su->ary(), []);
            }

            $login = $request->query->get('login', '');
        }

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
        $out .= $link_render->link_no_attr('password_reset',
            $pp->ary(), [],
            'Klik hier als je je paswoord vergeten bent.');
        $out .= '</p>';
        $out .= '</div>';

        $out .= '<input type="submit" class="btn btn-info btn-lg" ';
        $out .= 'value="Inloggen" name="zend">';

        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('login');

        return $this->render('login/login.html.twig', [
            'content'   => $out,
        ]);
    }
}
