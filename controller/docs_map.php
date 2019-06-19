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

        $row = $app['xdb']->get('doc', $map_id, $app['tschema']);

        if ($row)
        {
            $map_name = $row['data']['map_name'];
        }

        if (!$map_name)
        {
            $app['alert']->error('Onbestaande map id.');
            $app['link']->redirect('docs', $app['pp_ary'], []);
        }

        $rows = $app['xdb']->get_many(['agg_schema' => $app['tschema'],
            'agg_type' => 'doc',
            'data->>\'map_id\'' => $map_id,
            'access' => $app['item_access']->get_visible_ary_xdb()],
            'order by event_time asc');

        $docs = [];

        if (count($rows))
        {
            foreach ($rows as $row)
            {
                $data = $row['data'] + ['ts' => $row['event_time'], 'id' => $row['eland_id']];

                if ($row['agg_version'] > 1)
                {
                    $data['edit_count'] = $row['agg_version'] - 1;
                }

                $docs[] = $data;
            }
        }

        if ($app['s_admin'])
        {
            $app['btn_top']->add('docs_add', $app['pp_ary'],
                ['map_id' => $map_id], 'Document opladen');

            $app['btn_top']->edit('docs_map_edit', $app['pp_ary'],
                ['map_id' => $map_id], 'Map aanpassen');

            $app['btn_nav']->csv();
        }

        $app['heading']->add($app['link']->link_no_attr('docs', $app['pp_ary'], [], 'Documenten'));
        $app['heading']->add(': map "' . $map_name . '"');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="get">';
        $out .= '<div class="row">';
        $out .= '<div class="col-xs-12">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-search"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="q" name="q" value="';
        $out .= $q;
        $out .= '" ';
        $out .= 'placeholder="Zoeken">';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        if (count($docs))
        {
            $show_visibility = ($app['s_user']
                    && $app['intersystem_en'])
                || $app['s_admin'];

            $out .= '<div class="panel panel-default printview">';

            $out .= '<div class="table-responsive">';
            $out .= '<table class="table table-bordered ';
            $out .= 'table-striped table-hover footable csv" ';
            $out .= 'data-filter="#q" data-filter-minimum="1">';
            $out .= '<thead>';

            $out .= '<tr>';
            $out .= '<th data-sort-initial="true">';
            $out .= 'Naam</th>';
            $out .= '<th data-hide="phone, tablet">';
            $out .= 'Tijdstip</th>';

            if ($show_visibility)
            {
                $out .= '<th data-hide="phone, tablet">';
                $out .= 'Zichtbaarheid</th>';
            }

            $out .= $app['s_admin'] ? '<th data-hide="phone, tablet" data-sort-ignore="true">Acties</th>' : '';
            $out .= '</tr>';

            $out .= '</thead>';
            $out .= '<tbody>';

            foreach($docs as $d)
            {
                $did = $d['id'];

                $td = [];

                $td_c = '<a href="';
                $td_c .= $app['s3_url'] . $d['filename'];
                $td_c .= '" target="_self">';
                $td_c .= (isset($d['name']) && $d['name'] != '') ? $d['name'] : $d['org_filename'];
                $td_c .= '</a>';
                $td[] = $td_c;

                $td[] = $app['date_format']->get($d['ts'], 'min', $app['tschema']);

                if ($show_visibility)
                {
                    $td[] = $app['item_access']->get_label_xdb($d['access']);
                }

                if ($app['s_admin'])
                {
                    $td_c = $app['link']->link_fa('docs_edit', $app['pp_ary'],
                        ['doc_id' => $did], 'Aanpassen',
                        ['class' => 'btn btn-primary'], 'pencil');
                    $td_c .= '&nbsp;';
                    $td_c .= $app['link']->link_fa('docs_del', $app['pp_ary'],
                        ['doc_id' => $did], 'Verwijderen',
                        ['class' => 'btn btn-danger'], 'times');
                    $td[] = $td_c;
                }

                $out .= '<tr><td>';
                $out .= implode('</td><td>', $td);
                $out .= '</td></tr>';
            }

            $out .= '</tbody>';
            $out .= '</table>';

            $out .= '</div>';
            $out .= '</div>';
        }
        else
        {
            $out .= '<div class="panel panel-default">';
            $out .= '<div class="panel-heading">';
            $out .= '<p>Er zijn nog geen documenten opgeladen.</p>';
            $out .= '</div></div>';
        }

        $app['tpl']->add($out);
        $app['tpl']->menu('docs');

        return $app['tpl']->get($request);
    }
}
