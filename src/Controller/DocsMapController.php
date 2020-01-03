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
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocsMapController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
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

        $docs = [];

        $name = $db->fetchColumn('select name
            from ' . $pp->schema() . '.doc_maps
            where id = ?', [$id]);

        if (!$name)
        {
            throw new NotFoundHttpException('Documenten map met id ' . $id . ' niet gevonden.');
        }

        $stmt = $db->executeQuery('select *
            from ' . $pp->schema() . '.docs
            where access in (?)
                and map_id = ?
            order by name, original_filename asc',
            [$item_access_service->get_visible_ary_for_page(), $id],
            [Db::PARAM_STR_ARRAY, \PDO::PARAM_INT]);

        while ($row = $stmt->fetch())
        {
            $docs[] = $row;
        }

        $stmt_prev = $db->executeQuery('select m.id
            from ' . $pp->schema() . '.doc_maps m
            inner join ' . $pp->schema() . '.docs d
                on d.map_id = m.id
            where d.access in (?)
                and m.name < ?
            order by m.name desc
            limit 1',
        [$item_access_service->get_visible_ary_for_page(), $name],
        [Db::PARAM_STR_ARRAY, \PDO::PARAM_STR]);

        $prev = $stmt_prev->fetchColumn();

        $stmt_next = $db->executeQuery('select m.id
            from ' . $pp->schema() . '.doc_maps m
            inner join ' . $pp->schema() . '.docs d
                on d.map_id = m.id
            where d.access in (?)
                and m.name > ?
            order by m.name asc
            limit 1',
        [$item_access_service->get_visible_ary_for_page(), $name],
        [Db::PARAM_STR_ARRAY, \PDO::PARAM_STR]);

        $next = $stmt_next->fetchColumn();

        if ($pp->is_admin())
        {
            $btn_top_render->add('docs_add', $pp->ary(),
                ['map_id' => $id], 'Document opladen');

            $btn_top_render->edit('docs_map_edit', $pp->ary(),
                ['id' => $id], 'Map aanpassen');

            $btn_nav_render->csv();
        }

        $prev_ary = $prev ? ['id' => $prev] : [];
        $next_ary = $next ? ['id' => $next] : [];

        $btn_nav_render->nav('docs_map', $pp->ary(),
            $prev_ary, $next_ary, false);

        $btn_nav_render->nav_list('docs', $pp->ary(),
            [], 'Overzicht', 'files-o');

        $heading_render->add('Documenten map "');
        $heading_render->add($name . '"');
        $heading_render->fa('files-o');

        $out = '<div class="card bg-info">';
        $out .= '<div class="card-body">';

        $out .= '<form method="get">';
        $out .= '<div class="row">';
        $out .= '<div class="col-xs-12">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<i class="fa fa-search"></i>';
        $out .= '</span>';
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
            $show_access = ($pp->is_user()
                    && $config_service->get_intersystem_en($pp->schema()))
                || $pp->is_admin();

            $out .= '<div class="table-responsive">';
            $out .= '<table class="table table-bordered ';
            $out .= 'table-striped table-hover footable csv bg-default" ';
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
        }
        else
        {
            $out .= '<div class="card bg-default">';
            $out .= '<div class="card-body">';
            $out .= '<p>Er zijn nog geen documenten opgeladen.</p>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $menu_service->set('docs');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
