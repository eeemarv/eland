<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\LinkRender;
use App\Service\DateFormatService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\XdbService;


class DocsMapController extends AbstractController
{
    public function docs_map(
        Request $request,
        string $map_id,
        XdbService $xdb_service,
        AlertService $alert_service,
        LinkRender $link_render,
        BtnTopRender $btn_top_render,
        BtnNavRender $btn_nav_render,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        DateFormatService $date_format_service,
        MenuService $menu_service,
        ConfigService $config_service,
        PageParamsService $pp,
        string $env_s3_url
    ):Response
    {
        $q = $request->query->get('q', '');

        $row = $xdb_service->get('doc', $map_id, $pp->schema());

        if ($row)
        {
            $map_name = $row['data']['map_name'];
        }

        if (!$map_name)
        {
            $alert_service->error('Onbestaande map id.');
            $link_render->redirect('docs', $pp->ary(), []);
        }

        $rows = $xdb_service->get_many(['agg_schema' => $pp->schema(),
            'agg_type' => 'doc',
            'data->>\'map_id\'' => $map_id,
            'access' => $item_access_service->get_visible_ary_xdb()],
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

        if ($pp->is_admin())
        {
            $btn_top_render->add('docs_add', $pp->ary(),
                ['map_id' => $map_id], 'Document opladen');

            $btn_top_render->edit('docs_map_edit', $pp->ary(),
                ['map_id' => $map_id], 'Map aanpassen');

            $btn_nav_render->csv();
        }

        $heading_render->add_raw($link_render->link_no_attr('docs', $pp->ary(), [], 'Documenten'));
        $heading_render->add(': map "' . $map_name . '"');

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
            $show_visibility = ($pp->is_user()
                    && $config_service->get_intersystem_en($pp->schema()))
                || $pp->is_admin();

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

            $out .= $pp->is_admin() ? '<th data-hide="phone, tablet" data-sort-ignore="true">Acties</th>' : '';
            $out .= '</tr>';

            $out .= '</thead>';
            $out .= '<tbody>';

            foreach($docs as $d)
            {
                $did = $d['id'];

                $td = [];

                $td_c = '<a href="';
                $td_c .= $env_s3_url . $d['filename'];
                $td_c .= '" target="_self">';
                $td_c .= (isset($d['name']) && $d['name'] != '') ? $d['name'] : $d['org_filename'];
                $td_c .= '</a>';
                $td[] = $td_c;

                $td[] = $date_format_service->get($d['ts'], 'min', $pp->schema());

                if ($show_visibility)
                {
                    $td[] = $item_access_service->get_label_xdb($d['access']);
                }

                if ($pp->is_admin())
                {
                    $td_c = $link_render->link_fa('docs_edit', $pp->ary(),
                        ['doc_id' => $did], 'Aanpassen',
                        ['class' => 'btn btn-primary'], 'pencil');
                    $td_c .= '&nbsp;';
                    $td_c .= $link_render->link_fa('docs_del', $pp->ary(),
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

        $menu_service->set('docs');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
