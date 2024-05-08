<?php declare(strict_types=1);

namespace App\Controller\UsersConfig;

use App\Cache\ConfigCache;
use App\Command\UsersConfig\UsersConfigLeavingCommand;
use App\Form\Type\UsersConfig\UsersConfigLeavingType;
use App\Service\AlertService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
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
        ConfigCache $config_cache,
        PageParamsService $pp
    ):Response
    {
        if (!$config_cache->get_bool('users.leaving.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Leaving users not enabled.');
        }

        $command = new UsersConfigLeavingCommand();

        $config_cache->load_command($command, $pp->schema());

        $form = $this->createForm(UsersConfigLeavingType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $changed = $config_cache->store_command($command, $pp->schema());

            if ($changed)
            {
                $alert_service->success('Configuratie uitstappende leden aangepast');
            }
            else
            {
                $alert_service->Warning('Configuratie uitstappende leden niet gewijzigd');
            }

            return $this->redirectToRoute('users_config_leaving', $pp->ary());
        }

        return $this->render('users_config/users_config_leaving.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
