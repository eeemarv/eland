<?php declare(strict_types=1);

namespace App\Controller\Auth;

use App\Command\Auth\LoginCommand;
use App\Form\Post\Auth\LoginType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\LoginRepository;
use App\Repository\UserRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\VarRouteService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoginController extends AbstractController
{
    public function __invoke(
        Request $request,
        AlertService $alert_service,
        LoggerInterface $logger,
        MenuService $menu_service,
        LinkRender $link_render,
        ConfigService $config_service,
        AccountRender $account_render,
        UserRepository $user_repository,
        LoginRepository $login_repository,
        TranslatorInterface $translator,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr
    ):Response
    {
        $errors = [];

        $maintenance_enabled = $config_service->get('maintenance', $pp->schema()) ? true : false;
        $intersystem_enabled = $config_service->get_intersystem_en($pp->schema()) ? true : false;

        $location = $request->query->get('location', '');

        if (!$location
            || strpos($location, 'login') !== false
            || strpos($location, 'logout') !== false
            || $location === '/')
        {
            $location = '';
        }

        $login_command = new LoginCommand();

        if ($request->isMethod('GET')
            && $request->query->has('login'))
        {
            $login_command->login = $request->query->get('login');
        }

        $form = $this->createForm(LoginType::class, $login_command)
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $login_command = $form->getData();

            if (isset($login_command->is_master) && $login_command->is_master)
            {
                $su->set_master_login($pp->schema());

                $alert_service->success($translator->trans('login.success.master', [], 'alert'));

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

            $user = $user_repository->get($login_command->id, $pp->schema());

            if ($maintenance_enabled && $user['role'] !== 'admin')
            {
                $errors[] = $translator->trans('login.error.maintenance', [], 'alert');
            }

            if ($intersystem_enabled && $user['role'] === 'guest')
            {
                $errors[] = $translator->trans('login.error.intersystem_guest', [], 'alert');
            }

            if (!count($errors))
            {
                $su->set_login($pp->schema(), $login_command->id);

                $log_ary = [
                    'user_id'	=> $user['id'],
                    'code'	    => $user['code'],
                    'username'	=> $user['name'],
                    'schema' 	=> $pp->schema(),
                ];

                $agent = $request->server->get('HTTP_USER_AGENT');

                $logger->info('User ' .
                    $account_render->str_id($login_command->id, $pp->schema()) .
                    ' logged in, agent: ' . $agent, $log_ary);

                $login_repository->insert($login_command->id, $request, $pp->schema());
                $alert_service->success($translator->trans('login.success.user', [], 'alert'));

                if ($location)
                {
                    header('Location: ' . $location);
                    exit;
                }

                $link_render->redirect($vr->get('default'), $su->ary(), []);
            }

            $alert_service->error($errors);
        }

        if($maintenance_enabled)
        {
            $alert_service->warning($translator->trans('login.warning.maintenance', [], 'alert'));
        }

        $menu_service->set('login');

        return $this->render('auth/login.html.twig', [
            'form'      => $form->createView(),
            'schema'    => $pp->schema(),
        ]);
    }
}
