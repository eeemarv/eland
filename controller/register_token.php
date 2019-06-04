<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class register_token
{
    public function form(app $app):Response
    {
        return $app['legacy_route']->render('register_token');
    }
}
