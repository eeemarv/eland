<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Cache\ConfigCache;
use App\Form\Type\Del\DelType;
use App\Service\AlertService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ConfigLogoDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/logo/del',
        name: 'config_logo_del',
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
        ConfigCache $config_cache,
        AlertService $alert_service,
        PageParamsService $pp
    ):Response
    {
        $logo = $config_cache->get_str('system.logo', $pp->schema());

        if (!$logo)
        {
            throw new ConflictHttpException('No logo is configured for this system.');
        }

        $form = $this->createForm(DelType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $config_cache->set_str('system.logo', '', $pp->schema());

            $alert_service->success('Het logo is verwijderd.');
            return $this->redirectToRoute('config_logo', $pp->ary());
        }

        return $this->render('config/config_logo_del.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
