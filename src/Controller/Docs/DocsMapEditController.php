<?php declare(strict_types=1);

namespace App\Controller\Docs;

use App\Command\Docs\DocsMapCommand;
use App\Form\Type\Docs\DocsMapType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\DocRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class DocsMapEditController extends AbstractController
{
  #[Route(
    '/{system}/{role_short}/docs/map/{id}/edit',
    name: 'docs_map_edit',
    methods: ['GET', 'POST'],
    requirements: [
      'id'            => '%assert.id%',
      'system'        => '%assert.system%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'docs',
    ],
  )]

  public function __invoke(
    Request $request,
    int $id,
    DocRepository $doc_repository,
    ConfigService $config_service,
    TypeaheadService $typeahead_service,
    PageParamsService $pp,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'docs.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw new NotFoundHttpException('Documents module not enabled.');
    }

    $doc_map = $doc_repository->get_map(
      map_id: $id,
      schema: $pp->schema_o(),
    );

    $command = new DocsMapCommand();
    $command->id = $id;
    $command->name = $doc_map['name'];

    $form_options = [
      'render_omit' => $doc_map['name'],
      'validation_groups' => ['edit'],
    ];

    $form = $this->createForm(DocsMapType::class,
      $command, $form_options);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();

      $doc_repository->update_map_name(
        name: $command->name,
        map_id: $id,
        schema: $pp->schema_o(),
      );

      $typeahead_service->clear_cache($pp->schema());

      $this->addFlash('success', 'Map naam aangepast.');

      return $this->redirectToRoute('docs_map', [
        ...$pp->ary(),
        'id' => $id,
      ]);
    }

    return $this->render('docs/docs_map_edit.html.twig', [
      'form'      => $form->createView(),
      'doc_map'   => $doc_map,
    ]);
  }
}
