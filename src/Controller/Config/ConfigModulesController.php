<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Cache\ConfigCache;
use App\Command\Config\ConfigModulesCommand;
use App\Form\Type\Config\ConfigModulesType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ConfigModulesController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/modules',
        name: 'config_modules',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'config',
        ],
    )]

    public function __invoke(
        Request $request,
        AlertService $alert_service,
        ConfigCache $config_cache,
        PageParamsService $pp
    ):Response
    {
        $command = new ConfigModulesCommand();
        $config_cache->load_command($command, $pp->schema());

        $form = $this->createForm(ConfigModulesType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $changed = $config_cache->store_command($command, $pp->schema());

            if ($changed)
            {
                $alert_service->success('Modules aangepast');
            }
            else
            {
                $alert_service->warning('Modules niet gewijzigd');
            }

            return $this->redirectToRoute('config_modules', $pp->ary());
        }

        return $this->render('config/config_modules.html.twig', [
            'form'  => $form->createView(),
        ]);
    }
}
