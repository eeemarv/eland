<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class HomeController extends AbstractController
{
    public function __invoke(
        ConfigService $config_service,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        $out = '<div class="jumbotron">';
        $out .= '<h1>';
        $out .= $config_service->get('systemname', $pp->schema());
        $out .= '</h1>';
        $out .= '</div>';

        $menu_service->set('home');

        return $this->render('base/sidebar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}