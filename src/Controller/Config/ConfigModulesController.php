<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigModulesCommand;
use App\Form\Post\Config\ConfigModulesType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Routing\Annotation\Route;

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
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        $command = new ConfigModulesCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(ConfigModulesType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $config_service->store_command($command, $pp->schema());

            $alert_service->success('Modules aangepast.');
            return $this->redirectToRoute('config_modules', $pp->ary());
        }

        return $this->render('config/config_modules.html.twig', [
            'form'  => $form->createView(),
        ]);
    }
}
