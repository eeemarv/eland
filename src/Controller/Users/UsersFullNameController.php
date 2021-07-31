<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersFullNameCommand;
use App\Form\Post\Users\UsersFullNameType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class UsersFullNameController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/full-name',
        name: 'users_full_name',
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
        if (!$config_service->get_bool('users.fields.full_name.enabled', $pp->schema()))
        {
            throw new AccessDeniedHttpException('Full name module not enabled.');
        }

        $users_full_name_command = new UsersFullNameCommand();

        $self_edit = $config_service->get_bool('users.fields.full_name.self_edit', $pp->schema());


        $users_full_name_command->self_edit = $self_edit;

        $form = $this->createForm(UsersFullNameType::class,
            $users_full_name_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $users_full_name_command = $form->getData();

            $self_edit = $users_full_name_command->self_edit;

            $config_service->set_bool('users.fields.full_name.self_edit', $self_edit, $pp->schema());

            $alert_service->success('Volledige naam configuratie aangepast');
            return $this->redirectToRoute('users_full_name', $pp->ary());
        }

        return $this->render('users/users_full_name.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
