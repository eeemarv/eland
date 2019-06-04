<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class messages
{
    public function match(app $app):Response
    {
        return $app['legacy_route']->render('messages');
    }
}
