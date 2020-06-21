<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigNameCommand;
use App\Form\Post\Config\ConfigNameType;
use App\Form\Post\DelType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;

class ConfigLogoController extends AbstractController
{
    public function __invoke(
        Request $request,
        MenuService $menu_service,
        AlertService $alert_service,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        $menu_service->set('config');

        return $this->render('config/config_logo.html.twig', [
            'schema'    => $pp->schema(),
        ]);
    }
}
