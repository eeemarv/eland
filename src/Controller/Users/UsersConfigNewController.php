<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Config\UsersConfigNewCommand;
use App\Form\Post\Users\UsersConfigNewType;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class UsersConfigNewController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/config-new',
        name: 'users_config_new',
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
        LinkRender $link_render,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('users.new.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('New users not enabled.');
        }

        $users_config_new_command = new UsersConfigNewCommand();

        $days = $config_service->get_int('users.new.days', $pp->schema());
        $access = $config_service->get_str('users.new.access', $pp->schema());
        $access_list = $config_service->get_str('users.new.access_list', $pp->schema());
        $access_pane = $config_service->get_str('users.new.access_pane', $pp->schema());

        $users_config_new_command->days = $days;
        $users_config_new_command->access = $access;
        $users_config_new_command->access_list = $access_list;
        $users_config_new_command->access_pane = $access_pane;

        $form = $this->createForm(UsersConfigNewType::class,
            $users_config_new_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $users_config_new_command = $form->getData();

            $days = $users_config_new_command->days;
            $access = $users_config_new_command->access;
            $access_list = $users_config_new_command->access_list;
            $access_pane = $users_config_new_command->access_pane;

            $config_service->set_int('users.new.days', $days, $pp->schema());
            $config_service->set_str('users.new.access', $access, $pp->schema());
            $config_service->set_str('users.new.access_list', $access_list, $pp->schema());
            $config_service->set_str('users.new.access_pane', $access_pane, $pp->schema());

            $alert_service->success('Configuratie instappende leden aangepast');
            $link_render->redirect('users_config_new', $pp->ary(), []);
        }

        return $this->render('users/users_config_new.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
