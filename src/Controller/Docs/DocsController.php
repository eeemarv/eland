<?php declare(strict_types=1);

namespace App\Controller\Docs;

use App\Form\Filter\QTextSearchFilterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\DocRepository;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class DocsController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/docs',
        name: 'docs',
        priority: 20,
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
        ],
        defaults: [
            'module'        => 'docs',
        ],
    )]

    public function __invoke(
        Request $request,
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
        $maps = $doc_repository->get_maps($visible_ary, $pp->schema());
        $docs = $doc_repository->get_unmapped_docs($visible_ary, $pp->schema());

        $filter_form = $this->createForm(QTextSearchFilterType::class);
        $filter_form->handleRequest($request);

        $show_access = ($pp->is_user()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        return $this->render('docs/docs.html.twig', [
            'maps'          => $maps,
            'docs'          => $docs,
            'show_access'   => $show_access,
            'filter_form'   => $filter_form->createView(),
        ]);
    }
}
