<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class index
{
    public function index(app $app):Response
    {
        $app['menu']->set('index');

        return $app->render('index/index.html.twig', [
            'systems'       => $app['systems']->get_systems(),
        ]);
    }
}
