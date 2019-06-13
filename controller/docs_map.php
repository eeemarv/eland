<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class docs_map
{
    public function get(Request $request, app $app, string $map_id):Response
    {
        $q = $request->query->get('q', '');

        $app['tpl']->add($out);
        $app['tpl']->menu('docs');

        return $app['tpl']->get($request);
    }
}
