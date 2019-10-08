<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class NewsApproveController extends AbstractController
{
    public function news_approve(app $app, int $id, Db $db):Response
    {
        $data = [
            'approved'  => 't',
            'published' => 't',
        ];

        if ($db->update($app['pp_schema'] . '.news',
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
