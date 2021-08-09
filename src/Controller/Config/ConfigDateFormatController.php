<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigDateFormatCommand;
use App\Form\Post\Config\ConfigDateFormatType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Routing\Annotation\Route;

class ConfigDateFormatController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/date-format',
        name: 'config_date_format',
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
        $command = new ConfigDateFormatCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(ConfigDateFormatType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $config_service->store_command($command, $pp->schema());

            $alert_service->success('Datum- en tijdweergave aangepast.');

            return $this->redirectToRoute('config_date_format', $pp->ary());
        }

        return $this->render('config/config_date_format.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
