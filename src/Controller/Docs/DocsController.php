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

class DocsController extends AbstractController
{
    public function __invoke(
        DocRepository $doc_repository,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        ItemAccessService $item_access_service,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        $visible_ary = $item_access_service->get_visible_ary_for_page();
        // to do: filter after page load
        // $q = $request->query->get('q', '');

        $doc_maps = $doc_repository->get_maps($visible_ary, $pp->schema());
        $docs = $doc_repository->get_unmapped_docs($visible_ary, $pp->schema());

        if ($pp->is_admin())
        {
            $btn_top_render->add('docs_add', $pp->ary(),
                [], 'Document opladen');

            $btn_nav_render->csv();
        }

        $show_access = ($pp->is_user()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        $menu_service->set('docs');

        return $this->render('docs/docs_list.html.twig', [
            'doc_maps'      => $doc_maps,
            'docs'          => $docs,
            'show_access'   => $show_access,
            'schema'        => $pp->schema(),
        ]);
    }
}
