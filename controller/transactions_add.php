<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class transactions_add
{
    public function match(Request $request, app $app):Response
    {

        $app['tpl']->add($out);
        $app['tpl']->menu('transactions');

        return $app['tpl']->get($request);
    }
}
