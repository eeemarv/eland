<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class monitor
{
    public function get(app $app):Response
    {
        return $app['legacy_route']->render('monitor');
    }
}
