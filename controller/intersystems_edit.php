<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class intersystems_edit
{
    public function match(Request $request, app $app, int $id):Response
    {



        $app['tpl']->add($out);
        $app['tpl']->menu('intersystems');

        return $app['tpl']->get($request);
    }
}
