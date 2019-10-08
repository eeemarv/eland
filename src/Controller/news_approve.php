<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class news_approve
{
    public function news_approve(app $app, int $id):Response
    {
        $data = [
            'approved'  => 't',
            'published' => 't',
        ];

        if ($app['db']->update($app['pp_schema'] . '.news',
            $data, ['id' => $id]))
        {
            $app['alert']->success('Nieuwsbericht goedgekeurd en gepubliceerd.');
        }
        else
        {
            $app['alert']->error('Goedkeuren en publiceren nieuwsbericht mislukt.');
        }

        $app['link']->redirect($app['r_news'], $app['pp_ary'], ['id' => $id]);

        return new Response('');
    }
}
