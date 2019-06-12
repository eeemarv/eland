<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class forum_edit
{
    public function match(Request $request, app $app):Response
    {
        if (!$app['config']->get('forum_en', $app['tschema']))
        {
            $app['alert']->warning('De forum pagina is niet ingeschakeld.');

            $default_route = $app['config']->get('default_landing_page', $app['tschema']);
            $app['link']->redirect($default_route, $app['pp_ary'], []);
        }



        $app['tpl']->add($out);
        $app['tpl']->menu('forum');

        return $app['tpl']->get($request);
    }
}
