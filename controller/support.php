<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class support
{
    public function form(Request $request, app $app):Response
    {



        $app['tpl']->add($out);

        return $app['tpl']->get($request);
    }
}
