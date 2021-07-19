<?php declare(strict_types=1);

namespace App\Controller\Docs;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\MenuService;
use App\Render\BtnTopRender;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class DocsMapController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/docs/{id}',
        name: 'docs_map',
        priority: 20,
        methods: ['GET'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
        ],
        defaults: [
            'module'        => 'docs',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        LinkRender $link_render,
        BtnTopRender $btn_top_render,
        ItemAccessService $item_access_service,
        DateFormatService $date_format_service,
        MenuService $menu_service,
        ConfigService $config_service,
        PageParamsService $pp,
        string $env_s3_url
    ):Response
    {
        if (!$config_service->get_bool('docs.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Documents module not enabled.');
        }

        $q = $request->query->get('q', '');

        $docs = [];

        $doc_map = $db->fetchAssociative('select *
            from ' . $pp->schema() . '.doc_maps
            where id = ?',
            [$id], [\PDO::PARAM_INT]);

        if (!$doc_map)
        {
            throw new NotFoundHttpException('Documents map with id ' . $id . ' not found.');
        }

        $name = $doc_map['name'];

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

        $prev_id = $db->fetchOne('select m.id
            from ' . $pp->schema() . '.doc_maps m
            inner join ' . $pp->schema() . '.docs d
                on d.map_id = m.id
            where d.access in (?)
                and m.name < ?
            order by m.name desc
            limit 1',
        [$item_access_service->get_visible_ary_for_page(), $name],
        [Db::PARAM_STR_ARRAY, \PDO::PARAM_STR]);

        $next_id = $db->fetchOne('select m.id
            from ' . $pp->schema() . '.doc_maps m
            inner join ' . $pp->schema() . '.docs d
                on d.map_id = m.id
            where d.access in (?)
                and m.name > ?
            order by m.name asc
            limit 1',
        [$item_access_service->get_visible_ary_for_page(), $name],
        [Db::PARAM_STR_ARRAY, \PDO::PARAM_STR]);

        if ($pp->is_admin())
        {
            $btn_top_render->add('docs_add', $pp->ary(),
                ['map_id' => $id], 'Document opladen');

            $btn_top_render->edit('docs_map_edit', $pp->ary(),
                ['id' => $id], 'Map aanpassen');
        }

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
            $show_access = ($pp->is_user()
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
        else
        {
            $out .= '<div class="panel panel-default">';
            $out .= '<div class="panel-heading">';
            $out .= '<p>Er zijn nog geen documenten opgeladen.</p>';
            $out .= '</div></div>';
        }

        $menu_service->set('docs');

        return $this->render('docs/docs_map.html.twig', [
            'content'   => $out,
            'doc_map'   => $doc_map,
            'prev_id'   => $prev_id,
            'next_id'   => $next_id,
            'schema'    => $pp->schema(),
        ]);
    }
}
