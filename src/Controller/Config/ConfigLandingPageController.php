<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigLandingPageCommand;
use App\Form\Type\Config\ConfigLandingPageType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ConfigLandingPageController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/landing-page',
        name: 'config_landing_page',
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
        $command = new ConfigLandingPageCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(ConfigLandingPageType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $config_service->store_command($command, $pp->schema());

            $alert_service->success('Landingspagina aangepast.');
            return $this->redirectToRoute('config_landing_page', $pp->ary());
        }

        return $this->render('config/config_landing_page.html.twig', [
            'form'  => $form->createView(),
        ]);
    }
}
