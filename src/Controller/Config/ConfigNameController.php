<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Cache\ConfigCache;
use App\Command\Config\ConfigNameCommand;
use App\Form\Type\Config\ConfigNameType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
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
        ConfigCache $config_cache,
        PageParamsService $pp
    ):Response
    {
        $command = new ConfigNameCommand();
        $config_cache->load_command($command, $pp->schema());

        $form = $this->createForm(ConfigNameType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $changed = $config_cache->store_command($command, $pp->schema());

            if ($changed)
            {
                $alert_service->success('Naam systeem of hoofding paneel aangepast');
            }
            else
            {
                $alert_service->warning('Naam systeem of hoofding paneel niet gewijzigd');
            }

            return $this->redirectToRoute('config_name', $pp->ary());
        }

        return $this->render('config/config_name.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
