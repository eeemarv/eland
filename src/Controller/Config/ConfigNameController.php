<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigNameCommand;
use App\Form\Type\Config\ConfigNameType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Routing\Annotation\Route;

class ConfigNameController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config',
        name: 'config_name',
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
        $command = new ConfigNameCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(ConfigNameType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $config_service->store_command($command, $pp->schema());

            $alert_service->success('Naam systeem of hoofding paneel aangepast.');
            return $this->redirectToRoute('config_name', $pp->ary());
        }

        return $this->render('config/config_name.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
