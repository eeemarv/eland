<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class home_system
{
    public function home_system(app $app):Response
    {
        return $app['legacy_route']->render('home_system');
    }
}
