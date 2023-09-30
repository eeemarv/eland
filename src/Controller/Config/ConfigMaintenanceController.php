<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigMaintenanceCommand;
use App\Form\Type\Config\ConfigMaintenanceType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ConfigMaintenanceController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/maintenance',
        name: 'config_maintenance',
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
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        $command = new ConfigMaintenanceCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(ConfigMaintenanceType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $changed = $config_service->store_command($command, $pp->schema());

            if ($changed)
            {
                $alert_service->success('Onderhoudsmodus aangepast');
            }
            else
            {
                $alert_service->warning('Onderhoudsmodus niet gewijzigd');
            }

            return $this->redirectToRoute('config_maintenance', $pp->ary());
        }

        return $this->render('config/config_maintenance.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
