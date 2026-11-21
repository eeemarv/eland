<?php declare(strict_types=1);

namespace App\Controller\Docs;

use App\Form\Type\Filter\QTextSearchFilterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\DocRepository;
use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class DocsMapController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Guest,
    module: AppModule::Docs,
    path: '/docs/{id}',
    name: 'docs_map',
    priority: 20,
    methods: ['GET'],
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

    $visible_ary = $item_access_service->get_visible_ary_for_page();

    $doc_map = $doc_repository->get_map_with_prev_next(
      map_id: $id,
      visible_ary: $visible_ary,
      schema: $pp->schema_o(),
    );

		if ($doc_map === false)
		{
			throw $this->createNotFoundException(
        'Document map ' . $id . ' not found.'
      );
		}

    $docs = $doc_repository->get_docs_for_map_id(
      map_id: $id,
      visible_ary: $visible_ary,
      schema: $pp->schema_o(),
    );

    $filter_form = $this->createForm(QTextSearchFilterType::class);
    $filter_form->handleRequest($request);

    $show_access = ($pp->is_user()
      && $config_service->get_intersystem_en(
        schema: $pp->schema_o(),
      ))
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
