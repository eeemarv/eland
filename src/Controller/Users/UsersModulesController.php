<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersModulesCommand;
use App\Form\Type\Users\UsersModulesType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Routing\Annotation\Route;

class UsersModulesController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/modules',
        name: 'users_modules',
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
        $command = new UsersModulesCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(UsersModulesType::class, $command);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $config_service->store_command($command, $pp->schema());

            $alert_service->success('Submodules/velden leden aangepast');
            return $this->redirectToRoute('users_modules', $pp->ary());
        }

        return $this->render('users/users_modules.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
