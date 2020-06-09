<?php declare(strict_types=1);

namespace App\Controller\Docs;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\MenuService;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Repository\DocRepository;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DocsMapController extends AbstractController
{
    public function __invoke(
        int $id,
        DocRepository $doc_repository,
        BtnTopRender $btn_top_render,
        BtnNavRender $btn_nav_render,
        ItemAccessService $item_access_service,
        MenuService $menu_service,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        $visible_ary = $item_access_service->get_visible_ary_for_page();

        // to do: filter after page loaded
        // $q = $request->query->get('q', '');

        $doc_map = $doc_repository->get_map($id, $pp->schema());
        $map_name = $doc_map['name'];

        $docs = $doc_repository->get_docs_for_map_id($id, $visible_ary, $pp->schema());

        if (!count($docs))
        {
            throw new AccessDeniedHttpException('Access denied for map ' . $id);
        }

        $prev = $doc_repository->get_prev_map_id($map_name, $visible_ary, $pp->schema());
        $next = $doc_repository->get_next_map_id($map_name, $visible_ary, $pp->schema());

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

        $show_access = ($pp->is_user()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        $menu_service->set('docs');

        return $this->render('docs/docs_map.html.twig', [
            'doc_map'       => $doc_map,
            'docs'          => $docs,
            'show_access'   => $show_access,
            'schema'        => $pp->schema(),
        ]);
    }
}
