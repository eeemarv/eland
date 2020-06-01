<?php declare(strict_types=1);

namespace App\Controller\Docs;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\MenuService;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Repository\DocRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;

class DocsController extends AbstractController
{
    public function __invoke(
        DocRepository $doc_repository,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        // to do: filter after page load
        // $q = $request->query->get('q', '');

        $doc_maps = $doc_repository->get_visible_maps($pp->schema());
        $docs = $doc_repository->get_visible_unmapped_docs($pp->schema());

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
