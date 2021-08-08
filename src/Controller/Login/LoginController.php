<?php declare(strict_types=1);

namespace App\Controller\Login;

use App\Command\Login\LoginCommand;
use App\Form\Post\Login\LoginType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\AccountRender;
use App\Repository\UserRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\VarRouteService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Annotation\Route;

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
        UserRepository $user_repository,
        ConfigService $config_service,
        AlertService $alert_service,
        LoggerInterface $logger,
        AccountRender $account_render,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr
    ):Response
    {
        $location = $request->query->get('location', '');

        if (!$location
            || str_contains($location, 'login')
            || str_contains($location, 'logout')
            || $location === '/')
        {
            $location = '';
        }

        $command = new LoginCommand();
        $command->login = $request->query->get('login');
        $form = $this->createForm(LoginType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($command->is_master)
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

                return $this->redirectToRoute($vr->get('default'), $pp_ary);
            }

            if (!isset($command->id) || !$command->id)
            {
                throw new \LogicException('No user id set in validator.');
            }

            $su->set_login($pp->schema(), $command->id);

            $agent = $request->server->get('HTTP_USER_AGENT');
            $ip = $request->getClientIp();

            $user = $user_repository->get($command->id, $pp->schema());

            $log_ary = [
                'user_id'	=> $user['id'],
                'code'	    => $user['code'],
                'username'	=> $user['name'],
                'schema' 	=> $pp->schema(),
            ];

            $logger->info('User ' .
                $account_render->str_id($command->id, $pp->schema()) .
                ' logged in, agent: ' . $agent, $log_ary);

            if (isset($command->password_hashing_updated)
                && $command->password_hashing_updated
            )
            {
                $logger->info('Password hashing updated', $log_ary);
                error_log('Password hashing updated');
            }

            $user_repository->insert_login($command->id, $agent, $ip, $pp->schema());

            $alert_service->success('Je bent ingelogd.');

            if ($location)
            {
                return $this->redirect($location);
            }

            return $this->redirectToRoute($vr->get('default'), $su->ary());
        }

        if($config_service->get_bool('system.maintenance_en', $pp->schema()))
        {
            $alert_service->warning('De website is niet beschikbaar
                wegens onderhoudswerken.  Enkel admins kunnen inloggen', false);
        }

        if ($request->isMethod('GET') && $su->is_user())
        {
            if ($location)
            {
                if (stripos($location, $pp->system() . '/a/') === false)
                {
                    return $this->redirect($location);
                }
            }

            return $this->redirectToRoute($vr->get('default'), $su->ary());
        }

        return $this->render('login/login.html.twig', [
            'form'      => $form->createView(),
        ]);
    }
}
