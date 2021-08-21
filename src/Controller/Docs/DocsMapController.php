<?php declare(strict_types=1);

namespace App\Controller\Docs;

use App\Form\Filter\QTextSearchType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\DocRepository;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use Symfony\Component\HttpFoundation\Request;
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
        DocRepository $doc_repository,
        ItemAccessService $item_access_service,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('docs.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Documents module not enabled.');
        }

        $visible_ary = $item_access_service->get_visible_ary_for_page();
        $doc_map = $doc_repository->get_map_with_prev_next($id, $visible_ary, $pp->schema());
        $docs = $doc_repository->get_docs_for_map_id($id, $visible_ary, $pp->schema());

        $filter_form = $this->createForm(QTextSearchType::class);
        $filter_form->handleRequest($request);

        $show_access = ($pp->is_user()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        return $this->render('docs/docs_map.html.twig', [
            'filter_form'   => $filter_form->createView(),
            'docs'          => $docs,
            'doc_map'       => $doc_map,
            'id'            => $id,
            'prev_id'       => $doc_map['prev_id'],
            'next_id'       => $doc_map['next_id'],
            'show_access'   => $show_access,
        ]);
    }
}
