<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class contacts
{
    public function match(app $app):Response
    {
        return $app['legacy_route']->render('contacts');
    }
}
