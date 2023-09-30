<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigMailCommand;
use App\Form\Type\Config\ConfigMailType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ConfigMailController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/mail',
        name: 'config_mail',
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
        $command = new ConfigMailCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(ConfigMailType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $changed = $config_service->store_command($command, $pp->schema());

            if ($changed)
            {
                $alert_service->success('E-mail instellingen aangepast');
            }
            else
            {
                $alert_service->warning('E-mail instellingen niet gewijzigd');
            }

            return $this->redirectToRoute('config_mail', $pp->ary());
        }

        return $this->render('config/config_mail.html.twig', [
            'form'  => $form->createView(),
        ]);
    }
}
