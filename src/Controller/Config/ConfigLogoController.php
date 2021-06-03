<?php declare(strict_types=1);

namespace App\Controller\Config;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\MenuService;
use App\Service\AssetsService;
use App\Service\PageParamsService;
use Symfony\Component\Routing\Annotation\Route;

class ConfigLogoController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/logo',
        name: 'config_logo',
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
        AssetsService $assets_service,
        MenuService $menu_service,
        PageParamsService $pp
    ):Response
    {
        $assets_service->add([
            'fileupload',
            'upload_image.js',
        ]);

        $menu_service->set('config_name');

        return $this->render('config/config_logo.html.twig', [
            'schema'        => $pp->schema(),
        ]);
    }
}
