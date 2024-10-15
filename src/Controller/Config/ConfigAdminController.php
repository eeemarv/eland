<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigAdminCommand;
use App\Form\Type\Config\ConfigAdminType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ConfigAdminController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/admin',
        name: 'config_admin',
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
        $command = new ConfigAdminCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(ConfigAdminType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $config_service->store_command($command, $pp->schema());

            $alert_service->success('Admin instellingen aangepast.');
            return $this->redirectToRoute('config_admin', $pp->ary());
        }

        return $this->render('config/config_admin.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
