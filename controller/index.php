<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class index
{
    public function index(Request $request, app $app):Response
    {


        $app['menu']->set('index');

        return $app->render('base/index.html.twig', [
            'systems'       => $app['systems']->get_systems(),
        ]);
    }
}
