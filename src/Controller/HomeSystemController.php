<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\MenuService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class HomeSystemController extends AbstractController
{
    public function home_system(
        ConfigService $config_service,
        MenuService $menu_service
    ):Response
    {
        $out = '<div class="jumbotron">';
        $out .= '<h1>';
        $out .= $config_service->get('systemname', $pp->schema());
        $out .= '</h1>';
        $out .= '</div>';

        $menu_service->set('home_system');

        return $this->render('base/sidebar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
