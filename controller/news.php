<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use cnst\access as cnst_access;

class news
{
    public function list(Request $request, app $app):Response
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

        if($app['s_user'] || $app['s_admin'])
        {
            $app['btn_top']->add('news_add', $app['pp_ary'],
                [], 'Nieuws toevoegen');
        }

        $app['heading']->add('Nieuws');
        $app['heading']->fa('calendar-o');

        if ($app['s_admin'])
        {
            $app['btn_nav']->csv();
        }

        $app['btn_nav']->view('news_list', $app['pp_ary'],
            [], 'Lijst', 'align-justify', true);

        $app['btn_nav']->view('news_extended', $app['pp_ary'],
            [], 'Lijst met omschrijvingen', 'th-list', false);

        if (!count($news))
        {
            $out = '<div class="panel panel-default">';
            $out .= '<div class="panel-heading">';
            $out .= '<p>Er zijn momenteel geen nieuwsberichten.</p>';
            $out .= '</div></div>';

            $app['tpl']->add($out);
            $app['tpl']->menu('news');

            return $app['tpl']->get($request);
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
}
