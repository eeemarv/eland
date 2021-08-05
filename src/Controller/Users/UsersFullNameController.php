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

        $command = new UsersFullNameCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(UsersFullNameType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $config_service->store_command($command, $pp->schema());

            $alert_service->success('Volledige naam configuratie aangepast');
            return $this->redirectToRoute('users_full_name', $pp->ary());
        }

        return $this->render('users/users_full_name.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
