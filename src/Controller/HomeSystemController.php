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
        $out .= $app['config']->get('systemname', $app['pp_schema']);
        $out .= '</h1>';
        $out .= '</div>';

        $app['menu']->set('home_system');

        return $app->render('base/sidebar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}