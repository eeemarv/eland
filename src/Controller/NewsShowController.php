<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\DBAL\Connection as Db;

class NewsShowController extends AbstractController
{
    public function news_show(app $app, int $id, Db $db):Response
    {
        $show_visibility = ($app['pp_user']
                && $app['intersystem_en'])
            || $app['pp_admin'];

        $news_access_ary = $no_access_ary = [];

        $rows = $xdb_service->get_many([
            'agg_schema' => $app['pp_schema'],
            'agg_type' => 'news_access',
        ]);

        foreach ($rows as $row)
        {
            $access = $row['data']['access'];
            $news_access_ary[$row['eland_id']] = $access;
        }

        $query = 'select * from ' . $app['pp_schema'] . '.news';

        if(!$app['pp_admin'])
        {
            $query .= ' where approved = \'t\'';
        }

        $query .= ' order by itemdate ';
        $query .= $config_service->get('news_order_asc', $app['pp_schema']) === '1' ? 'asc' : 'desc';

        $st = $db->prepare($query);
        $st->execute();

        while ($row = $st->fetch())
        {
            $news_id = $row['id'];
            $news[$news_id] = $row;

            if (!isset($news_access_ary[$news_id]))
            {
                $xdb_service->set('news_access', (string) $news_id, [
                    'access' => 'interlets',
                ], $app['pp_schema']);

                $news[$news_id]['access'] = 'interlets';
            }
            else
            {
                $news[$news_id]['access'] = $news_access_ary[$news_id];
            }

            if (!$item_access_service->is_visible_xdb($news[$news_id]['access']))
            {
                unset($news[$news_id]);
                $no_access_ary[$news_id] = true;
            }
        }

        if (!isset($news[$id]))
        {
            throw new NotFoundHttpException('Dit nieuwsbericht bestaat niet.');
        }

        $news_item = $news[$id];

        if (!$app['pp_admin'] && !$news_item['approved'])
        {
            $alert_service->error('Je hebt geen toegang tot dit nieuwsbericht.');
            $link_render->redirect($app['r_news'], $app['pp_ary'], []);
        }

        if (isset($no_access_ary[$id]))
        {
            $alert_service->error('Je hebt geen toegang tot dit nieuwsbericht.');
            $link_render->redirect($app['r_news'], $app['pp_ary'], []);
        }

        $next = $prev = $current_news = false;

        foreach($news as $nid => $ndata)
        {
            if ($current_news)
            {
                $next = $nid;
                break;
            }

            if ($id == $nid)
            {
                $current_news = true;
                continue;
            }

            $prev = $nid;
        }

        if($app['pp_admin'])
        {
            $btn_top_render->edit('news_edit', $app['pp_ary'],
                ['id' => $id], 'Nieuwsbericht aanpassen');

            $btn_top_render->del('news_del', $app['pp_ary'],
                ['id' => $id], 'Nieuwsbericht verwijderen');

            if (!$news_item['approved'])
            {
                $btn_top_render->approve('news_approve', $app['pp_ary'],
                    ['id' => $id], 'Nieuwsbericht goedkeuren en publiceren');
            }
        }

        $prev_ary = $prev ? ['id' => $prev] : [];
        $next_ary = $next ? ['id' => $next] : [];

        $btn_nav_render->nav('news_show', $app['pp_ary'],
            $prev_ary, $next_ary, false);

        $btn_nav_render->nav_list($app['r_news'], $app['pp_ary'],
            [], 'Lijst', 'calendar-o');

        $heading_render->add('Nieuwsbericht: ' . $news_item['headline']);
        $heading_render->fa('calendar-o');

        $out = '<div class="panel panel-default printview">';
        $out .= '<div class="panel-body';
        $out .= $news_item['approved'] ? '' : ' bg-inactive';
        $out .= '">';

        $out .= '<dl>';

        if ($app['pp_admin'])
        {
            $out .= '<dt>Goedgekeurd en gepubliceerd door Admin</dt>';
            $out .= '<dd>';
            $out .= $news_item['approved'] ? 'Ja' : 'Nee';
            $out .= '</dd>';
        }

        $out .= '<dt>Agendadatum</dt>';

        $out .= '<dd>';

        if ($news_item['itemdate'])
        {
            $out .= $date_format_service->get($news_item['itemdate'], 'day', $app['pp_schema']);
        }
        else
        {
            $out .= '<i class="fa fa-times"></i>';
        }

        $out .= '</dd>';

        $out .= '<dt>Behoud na datum?</dt>';
        $out .= '<dd>';
        $out .= $news_item['sticky'] ? 'Ja' : 'Nee';
        $out .= '</dd>';

        $out .= '<dt>Locatie</dt>';
        $out .= '<dd>';

        if ($news_item['location'])
        {
            $out .= htmlspecialchars($news_item['location'], ENT_QUOTES);
        }
        else
        {
            $out .= '<i class="fa fa-times"></i>';
        }

        $out .= '</dd>';

        $out .= '<dt>Bericht/Details</dt>';
        $out .= '<dd>';
        $out .= nl2br(htmlspecialchars($news_item['newsitem'],ENT_QUOTES));
        $out .= '</dd>';

        if ($show_visibility)
        {
            $out .= '<dt>Zichtbaarheid</dt>';
            $out .= '<dd>';
            $out .= $item_access_service->get_label_xdb($news_item['access']);
            $out .= '</dd>';
        }

        $out .= '<dt>Ingegeven door</dt>';
        $out .= '<dd>';
        $out .= $account_render->link($news_item['id_user'], $app['pp_ary']);
        $out .= '</dd>';

        $out .= '</dl>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('news');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
