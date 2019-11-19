<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\MenuService;
use App\Render\HeadingRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\XdbService;
use Doctrine\DBAL\Connection as Db;

class DocsController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        XdbService $xdb_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        DateFormatService $date_format_service,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp,
        MenuService $menu_service,
        string $env_s3_url
    ):Response
    {
        $q = $request->query->get('q', '');

        $maps = $docs = [];

        $stmt = $db->executeQuery('select id, name
            from ' . $pp->schema() . '.doc_maps
            order by name asc');

        while ($row = $stmt->fetch())
        {
            $maps[$row['id']] = [
                'name'          => $row['name'],
                'doc_count'     => 0,
            ];
        }

        $stmt = $db->executeQuery('select *
            from ' . $pp->schema() . '.docs
            where access in (?)
            order by name, original_filename asc',
            [$item_access_service->get_visible_ary_for_page()],
            [Db::PARAM_STR_ARRAY]);

        while ($row = $stmt->fetch())
        {
            if (isset($row['map_id']))
            {
                $maps[$row['map_id']]['doc_count']++;
                continue;
            }

            $docs[] = $row;
        }

        if ($pp->is_admin())
        {
            $btn_top_render->add('docs_add', $pp->ary(),
                [], 'Document opladen');

            $btn_nav_render->csv();
        }

        $heading_render->add('Documenten');
        $heading_render->fa('files-o');

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

        if (count($maps))
        {
            $maps_table = '<div class="panel panel-default printview">';

            $maps_table .= '<div class="table-responsive">';
            $maps_table .= '<table class="table table-bordered table-striped table-hover footable"';
            $maps_table .= ' data-filter="#q" data-filter-minimum="1">';
            $maps_table .= '<thead>';

            $maps_table .= '<tr>';
            $maps_table .= '<th data-sort-initial="true">Map</th>';
            $maps_table .= $pp->is_admin() ? '<th data-sort-ignore="true">Aanpassen</th>' : '';
            $maps_table .= '</tr>';

            $maps_table .= '</thead>';
            $maps_table .= '<tbody>';

            $maps_table_rows = '';

            foreach($maps as $map_id => $map)
            {
                if (!$map['doc_count'])
                {
                    continue;
                }

                $td = [];

                $td[] = $link_render->link_no_attr('docs_map', $pp->ary(),
                    ['id' => $map_id], $map['name'] . ' (' . $map['doc_count'] . ')');

                if ($pp->is_admin())
                {
                    $td[] = $link_render->link_fa('docs_map_edit', $pp->ary(),
                        ['id' => $map_id], 'Aanpassen',
                        ['class' => 'btn btn-primary'], 'pencil');
                }

                $maps_table_rows .= '<tr class="info"><td>';
                $maps_table_rows .= implode('</td><td>', $td);
                $maps_table_rows .= '</td></tr>';
            }

            if ($maps_table_rows !== '')
            {
                $out .= $maps_table;
                $out .= $maps_table_rows;

                $out .= '</tbody>';
                $out .= '</table>';

                $out .= '</div>';
                $out .= '</div>';
            }
        }

        if (count($docs))
        {
            $show_access = ($pp->is_user()
                    && $config_service->get_intersystem_en($pp->schema()))
                || $pp->is_admin();

            $out  .= '<div class="panel panel-default printview">';

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

            if ($show_access)
            {
                $out .= '<th data-hide="phone, tablet">';
                $out .= 'Zichtbaarheid</th>';
            }

            $out .= $pp->is_admin() ? '<th data-hide="phone, tablet" data-sort-ignore="true">Acties</th>' : '';
            $out .= '</tr>';

            $out .= '</thead>';
            $out .= '<tbody>';

            foreach($docs as $doc)
            {
                $td = [];

                $td_c = '<a href="';
                $td_c .= $env_s3_url . $doc['filename'];
                $td_c .= '" target="_self">';
                $td_c .= htmlspecialchars($doc['name'] ?? $doc['original_filename'], ENT_QUOTES);
                $td_c .= '</a>';
                $td[] = $td_c;

                $td[] = $date_format_service->get($doc['created_at'], 'min', $pp->schema());

                if ($show_access)
                {
                    $td[] = $item_access_service->get_label($doc['access']);
                }

                if ($pp->is_admin())
                {
                    $td_c = $link_render->link_fa('docs_edit', $pp->ary(),
                        ['id' => $doc['id']], 'Aanpassen',
                        ['class' => 'btn btn-primary'], 'pencil');
                    $td_c .= '&nbsp;';
                    $td_c .= $link_render->link_fa('docs_del', $pp->ary(),
                        ['id' => $doc['id']], 'Verwijderen',
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
        else if (!count($maps))
        {
            $out .= '<div class="panel panel-default">';
            $out .= '<div class="panel-heading">';
            $out .= '<p>Er zijn nog geen documenten opgeladen.</p>';
            $out .= '</div></div>';
        }

        $menu_service->set('docs');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
