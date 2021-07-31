<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersConfigLeavingCommand;
use App\Form\Post\Users\UsersConfigLeavingType;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class UsersConfigLeavingController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/config-leaving',
        name: 'users_config_leaving',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        AlertService $alert_service,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('users.leaving.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Leaving users not enabled.');
        }

        $users_config_leaving_command = new UsersConfigLeavingCommand();

        $access = $config_service->get_str('users.leaving.access', $pp->schema());
        $access_list = $config_service->get_str('users.leaving.access_list', $pp->schema());
        $access_pane = $config_service->get_str('users.leaving.access_pane', $pp->schema());
        $equilibrium = $config_service->get_int('accounts.equilibrium', $pp->schema());
        $auto_deactivate = $config_service->get_bool('users.leaving.auto_deactivate', $pp->schema());
        $transactions_enabled = $config_service->get_bool('transactions.enabled', $pp->schema());

        $users_config_leaving_command->equilibrium = $equilibrium;
        $users_config_leaving_command->auto_deactivate = $auto_deactivate;
        $users_config_leaving_command->access = $access;
        $users_config_leaving_command->access_list = $access_list;
        $users_config_leaving_command->access_pane = $access_pane;

        $form = $this->createForm(UsersConfigLeavingType::class,
            $users_config_leaving_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $users_config_leaving_command = $form->getData();

            $equilibrium = $users_config_leaving_command->equilibrium;
            $auto_deactivate = $users_config_leaving_command->auto_deactivate;
            $access = $users_config_leaving_command->access;
            $access_list = $users_config_leaving_command->access_list;
            $access_pane = $users_config_leaving_command->access_pane;

            $config_service->set_str('users.leaving.access', $access, $pp->schema());
            $config_service->set_str('users.leaving.access_list', $access_list, $pp->schema());
            $config_service->set_str('users.leaving.access_pane', $access_pane, $pp->schema());

            if ($transactions_enabled)
            {
                $config_service->set_int('accounts.equilibrium', $equilibrium, $pp->schema());
                $config_service->set_bool('users.leaving.auto_deactivate', $auto_deactivate, $pp->schema());
            }

            $alert_service->success('Configuratie uitstappende leden aangepast');
            $this->redirectToRoute('users_config_leaving', $pp->ary());
        }

        return $this->render('users/users_config_leaving.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
