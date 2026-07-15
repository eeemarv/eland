<?php declare(strict_types=1);

namespace App\Controller\UniqueCheck;

use App\Repository\DocRepository;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UniqueCheckDocMapNamesController extends AbstractController
{
  use EtagJsonResponseTrait;

  #[Route(
    '/{schema}/{role_short}/unique-check/doc-map-names',
    name: 'unique_check_doc_map_names',
    methods: ['GET'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
  )]

  public function __invoke(
    DocRepository $doc_repository,
    Request $request,
    PageParamsService $pp,
  ):Response
  {
    $map_names = $doc_repository->get_doc_map_names(
      schema: $pp->schema_o(),
    );

    return $this->etagJsonResponse($request, $map_names);
  }
}
