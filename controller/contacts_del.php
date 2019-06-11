<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class contacts_del
{
    public function match(Request $request, app $app, int $id):Response
    {


        $app['tpl']->add($out);
        $app['tpl']->menu('contacts');

        return $app['tpl']->get($request);
    }
}
