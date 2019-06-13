<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class docs_map_edit
{
    public function match(Request $request, app $app, string $map_id):Response
    {


        $app['tpl']->add($out);
        $app['tpl']->menu('docs');

        return $app['tpl']->get($request);
    }
}
