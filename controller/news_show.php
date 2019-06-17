<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class news_show
{
    public function get(Request $request, app $app, int $id):Response
    {
        $show_visibility = ($app['s_user']
                && $app['intersystem_en'])
            || $app['s_admin'];

        $news_access_ary = $no_access_ary = [];

        $rows = $app['xdb']->get_many([
            'agg_schema' => $app['tschema'],
            'agg_type' => 'news_access',
        ]);

        foreach ($rows as $row)
        {
            $access = $row['data']['access'];
            $news_access_ary[$row['eland_id']] = $access;
        }

        $query = 'select * from ' . $app['tschema'] . '.news';

        if(!$app['s_admin'])
        {
            $query .= ' where approved = \'t\'';
        }

        $query .= ' order by itemdate ';
        $query .= $app['config']->get('news_order_asc', $app['tschema']) === '1' ? 'asc' : 'desc';

        $st = $app['db']->prepare($query);
        $st->execute();

        while ($row = $st->fetch())
        {
            $news_id = $row['id'];
            $news[$news_id] = $row;

            if (!isset($news_access_ary[$news_id]))
            {
                $app['xdb']->set('news_access', $news_id, [
                    'access' => 'interlets',
                ], $app['tschema']);

                $news[$news_id]['access'] = 'interlets';
            }
            else
            {
                $news[$news_id]['access'] = $news_access_ary[$news_id];
            }

            if (!$app['item_access']->is_visible_xdb($news[$news_id]['access']))
            {
                unset($news[$news_id]);
                $no_access_ary[$news_id] = true;
            }
        }

        if (!isset($news[$id]))
        {
            $app['alert']->error('Dit nieuwsbericht bestaat niet.');
            $app['link']->redirect('news', $app['pp_ary'], []);
        }

        $news_item = $news[$id];

        if (!$app['s_admin'] && !$news_item['approved'])
        {
            $app['alert']->error('Je hebt geen toegang tot dit nieuwsbericht.');
            $app['link']->redirect('news_list', $app['pp_ary'], []);
        }

        if (isset($no_access_ary[$id]))
        {
            $app['alert']->error('Je hebt geen toegang tot dit nieuwsbericht.');
            $app['link']->redirect('news_list', $app['pp_ary'], []);
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

        if($app['s_admin'])
        {
            $app['btn_top']->edit('news_edit', $app['pp_ary'],
                ['id' => $id], 'Nieuwsbericht aanpassen');

            $app['btn_top']->del('news_del', $app['pp_ary'],
                ['id' => $id], 'Nieuwsbericht verwijderen');

            if (!$news_item['approved'])
            {
                $app['btn_top']->approve('news_approve', $app['pp_ary'],
                    ['id' => $id], 'Nieuwsbericht goedkeuren en publiceren');
            }
        }

        $prev_ary = $prev ? ['id' => $prev] : [];
        $next_ary = $next ? ['id' => $next] : [];

        $app['btn_nav']->nav('news_show', $app['pp_ary'],
            $prev_ary, $next_ary, false);

        $app['btn_nav']->nav_list('news_list', $app['pp_ary'],
            [], 'Lijst', 'calendar-o');

        $app['heading']->add('Nieuwsbericht: ' . htmlspecialchars($news_item['headline'], ENT_QUOTES));
        $app['heading']->fa('calendar-o');

        $out = '<div class="panel panel-default printview">';
        $out .= '<div class="panel-body';
        $out .= $news_item['approved'] ? '' : ' bg-inactive';
        $out .= '">';

        $out .= '<dl>';

        if ($app['s_admin'])
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
            $out .= $app['date_format']->get($news_item['itemdate'], 'day', $app['tschema']);
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
            $out .= $app['item_access']->get_label_xdb($news_item['access']);
            $out .= '</dd>';
        }

        $out .= '<dt>Ingegeven door</dt>';
        $out .= '<dd>';
        $out .= $app['account']->link($news_item['id_user'], $app['pp_ary']);
        $out .= '</dd>';

        $out .= '</dl>';

        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('news');

        return $app['tpl']->get($request);
    }
}
