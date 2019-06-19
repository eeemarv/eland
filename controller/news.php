<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class news
{
    public function list(Request $request, app $app):Response
    {
        $news = $this->get_data($app);

        $this->set_heading_and_btns($app, true);

        $show_visibility = ($app['s_user']
                && $app['intersystem_en'])
            || $app['s_admin'];

        if ($app['s_admin'])
        {
            $app['btn_nav']->csv();
        }

        if (!count($news))
        {
            return $this->no_news($request, $app);
        }

        $out = '<div class="panel panel-warning printview">';
        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-striped ';
        $out .= 'table-hover table-bordered footable csv">';

        $out .= '<thead>';
        $out .= '<tr>';
        $out .= '<th>Titel</th>';
        $out .= '<th data-hide="phone" ';
        $out .= 'data-sort-initial="descending">Agendadatum</th>';
        $out .= $app['s_admin'] ? '<th data-hide="phone">Goedgekeurd</th>' : '';
        $out .= $show_visibility ? '<th data-hide="phone, tablet">Zichtbaar</th>' : '';
        $out .= '</tr>';
        $out .= '</thead>';

        $out .= '<tbody>';

        foreach ($news as $n)
        {
            $out .= '<tr';
            $out .= $n['approved'] ? '' : ' class="inactive"';
            $out .= '>';

            $out .= '<td>';

            $out .= $app['link']->link_no_attr('news_show', $app['pp_ary'],
                ['id' => $n['id']], $n['headline']);

            $out .= '</td>';

            $out .= $app['date_format']->get_td($n['itemdate'], 'day', $app['tschema']);

            if ($app['s_admin'])
            {
                $out .= '<td>';
                $out .= $n['approved'] ? 'Ja' : 'Nee';
                $out .= '</td>';
            }

            if ($show_visibility)
            {
                $out .= '<td>';
                $out .= $app['item_access']->get_label_xdb($n['access']);
                $out .= '</td>';
            }

            $out .= '</tr>';
        }

        $out .= '</tbody>';
        $out .= '</table></div></div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('news');

        return $app['tpl']->get($request);
    }

    public function extended(Request $request, app $app):Response
    {
        $news = $this->get_data($app);

        $this->set_heading_and_btns($app, false);

        $show_visibility = ($app['s_user']
                && $app['intersystem_en'])
            || $app['s_admin'];

        if (!count($news))
        {
            return $this->no_news($request, $app);
        }

        $out = '';

        foreach ($news as $n)
        {
            $out .=  '<div class="panel panel-info printview">';
            $out .=  '<div class="panel-body';
            $out .=  $n['approved'] ? '' : ' bg-inactive';
            $out .=  '">';

            $out .=  '<div class="media">';
            $out .=  '<div class="media-body">';
            $out .=  '<h2 class="media-heading">';

            $out .=  $app['link']->link_no_attr('news_show', $app['pp_ary'],
                ['id' => $n['id']], $n['headline']);

            $out .=  '</h2>';

            if (!$n['approved'])
            {
                $out .=  '<p class="text-warning">';
                $out .=  '<strong>';
                $out .=  'Dit nieuwsbericht wacht op goedkeuring en publicatie door een admin';
                $out .=  '</strong>';
                $out .=  '</p>';
            }

            $out .=  '<dl>';

            $out .=  '<dt>';
            $out .=  'Agendadatum';
            $out .=  '</dt>';
            $out .=  '<dd>';

            if ($n['itemdate'])
            {
                $out .=  $app['date_format']->get($n['itemdate'], 'day', $app['tschema']);

                $out .=  '<br><i>';

                if ($n['sticky'])
                {
                    $out .=  'Dit nieuwsbericht blijft behouden na deze datum.';
                }
                else
                {
                    $out .=  'Dit nieuwsbericht wordt automatisch gewist na deze datum.';
                }

                $out .=  '</i>';

            }
            else
            {
                $out .=  '<i class="fa fa-times></i>';
            }

            $out .=  '</dd>';

            $out .=  '<dt>';
            $out .=  'Locatie';
            $out .=  '</dt>';
            $out .=  '<dd>';

            if ($n['location'])
            {
                $out .=  htmlspecialchars($n['location'], ENT_QUOTES);
            }
            else
            {
                $out .=  '<i class="fa fa-times"></i>';
            }

            $out .=  '</dd>';

            $out .=  '</dl>';

            $out .=  '<h4>Bericht/Details</h4>';
            $out .=  '<p>';
            $out .=  nl2br(htmlspecialchars($n['newsitem'],ENT_QUOTES));
            $out .=  '</p>';

            $out .=  '<dl>';

            if ($show_visibility)
            {
                $out .=  '<dt>';
                $out .=  'Zichtbaarheid';
                $out .=  '</dt>';
                $out .=  '<dd>';
                $out .=  $app['item_access']->get_label_xdb($n['access']);
                $out .=  '</dd>';
            }

            $out .=  '</dl>';

            $out .=  '</div>';
            $out .=  '</div>';
            $out .=  '</div>';

            $out .=  '<div class="panel-footer">';
            $out .=  '<p><i class="fa fa-user"></i> ';

            $out .=  $app['account']->link($n['id_user'], $app['pp_ary']);

            if ($app['s_admin'])
            {
                $out .=  '<span class="inline-buttons pull-right hidden-xs">';

                if (!$n['approved'])
                {
                    $out .=  $app['link']->link_fa('news_approve', $app['pp_ary'],
                        ['id' => $n['id']], 'Goedkeuren en publiceren',
                        ['class' => 'btn btn-warning btn-xs'], 'check');
                }

                $out .=  $app['link']->link_fa('news_edit', $app['pp_ary'],
                    ['id' => $n['id']], 'Aanpassen',
                    ['class' => 'btn btn-primary btn-xs'], 'pencil');

                $out .=  $app['link']->link_fa('news_del', $app['pp_ary'],
                    ['id' => $n['id']], 'Verwijderen',
                    ['class' => 'btn btn-danger btn-xs'], 'times');

                $out .=  '</span>';
            }

            $out .=  '</p>';
            $out .=  '</div>';
            $out .=  '</div>';
        }

        $app['tpl']->add($out);
        $app['tpl']->menu('news');

        return $app['tpl']->get($request);
    }

    private function get_data(app $app):array
    {
        $news = $news_access_ary = [];

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
            }
        }

        return $news;
    }

    private function set_heading_and_btns(app $app, bool $is_list):void
    {
        if($app['s_user'] || $app['s_admin'])
        {
            $app['btn_top']->add('news_add', $app['pp_ary'],
                [], 'Nieuws toevoegen');
        }

        $app['heading']->add('Nieuws');
        $app['heading']->fa('calendar-o');

        $app['btn_nav']->view('news_list', $app['pp_ary'],
            [], 'Lijst', 'align-justify', $is_list);

        $app['btn_nav']->view('news_extended', $app['pp_ary'],
            [], 'Lijst met omschrijvingen', 'th-list', !$is_list);
    }

    private function no_news(Request $request, app $app):Response
    {
        $out = '<div class="panel panel-default">';
        $out .= '<div class="panel-heading">';
        $out .= '<p>Er zijn momenteel geen nieuwsberichten.</p>';
        $out .= '</div></div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('news');

        return $app['tpl']->get($request);
    }
}
