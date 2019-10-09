<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class HomeSystemController extends AbstractController
{
    public function home_system(app $app):Response
    {
        $out = '<div class="jumbotron">';
        $out .= '<h1>';
        $out .= $config_service->get('systemname', $app['pp_schema']);
        $out .= '</h1>';
        $out .= '</div>';

        $menu_service->set('home_system');

        return $this->render('base/sidebar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
