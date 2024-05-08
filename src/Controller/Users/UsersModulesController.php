<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cache\ConfigCache;
use App\Command\Users\UsersModulesCommand;
use App\Form\Type\Users\UsersModulesType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
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
        ConfigCache $config_cache,
        PageParamsService $pp
    ):Response
    {
        $command = new UsersModulesCommand();
        $config_cache->load_command($command, $pp->schema());

        $form = $this->createForm(UsersModulesType::class, $command);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $changed = $config_cache->store_command($command, $pp->schema());

            if ($changed)
            {
                $alert_service->success('Submodules/velden leden aangepast');
            }
            else
            {
                $alert_service->warning('Submodules/velden leden niet gewijzigd');
            }

            return $this->redirectToRoute('users_modules', $pp->ary());
        }

        return $this->render('users/users_modules.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
