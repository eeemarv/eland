<?php declare(strict_types=1);

namespace App\Controller\UsersConfig;

use App\Cache\ConfigCache;
use App\Command\UsersConfig\UsersConfigNameCommand;
use App\Form\Type\UsersConfig\UsersConfigNameType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersConfigNameController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/config/name',
        name: 'users_config_name',
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
        $command = new UsersConfigNameCommand();
        $config_cache->load_command($command, $pp->schema());

        $form = $this->createForm(UsersConfigNameType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $changed = $config_cache->store_command($command, $pp->schema());

            if ($changed)
            {
                $alert_service->success('Gebruikersnaam configuratie aangepast');
            }
            else
            {
                $alert_service->warning('Gebruikersnaam configuratie niet gewijzigd');
            }

            return $this->redirectToRoute('users_config_name', $pp->ary());
        }

        return $this->render('users_config/users_config_name.html.twig', [
            'form'  => $form->createView(),
        ]);
    }
}
