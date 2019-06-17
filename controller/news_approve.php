<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class news_approve
{
    public function get(Request $request, app $app, int $id):Response
    {
        $data = [
            'approved'  => 't',
            'published' => 't',
        ];

        if ($app['db']->update($app['tschema'] . '.news',
            $data, ['id' => $id]))
        {
            $app['alert']->success('Nieuwsbericht goedgekeurd en gepubliceerd.');
        }
        else
        {
            $app['alert']->error('Goedkeuren en publiceren nieuwsbericht mislukt.');
        }

        $app['link']->redirect('news_list', $app['pp_ary'], ['id' => $id]);

        $app['tpl']->menu('news');
        return $app['tpl']->get($request);
    }
}
