<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class transactions
{
    public function get(app $app, int $id):Response
    {
        return $app['legacy_route']->render('transactions');
    }
}
