<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class index
{
    public function index(app $app):Response
    {
        $app['menu']->set('index');

        $schemas = $app['systems']->get_schemas();

        sort($schemas);

        return $app->render('index/index.html.twig', [
            'schemas'       => $schemas,
        ]);
    }
}
