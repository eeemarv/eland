<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class export
{
    public function get(app $app):Response
    {
        return $app['legacy_route']->render('export');
    }
}
