<?php declare(strict_types=1);

namespace App\Controller\Autocomplete;

use App\Repository\DocRepository;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class AutocompleteDocMapNamesController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/autocomplete/doc-map-names',
    name: 'autocomplete_doc_map_names',
    methods: ['GET'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'docs',
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

    $json = json_encode($map_names);
    $etag = hash('crc32b', $json);

    $response = new Response();

    $response->setContent($json);
    $response->headers->set('Content-Type', 'application/json');
    $response->setEtag($etag);
    $response->setPublic();
    // if etag is the same, removes content and sets 304 Not Modified
    $response->isNotModified($request);

    return $response;
  }
}
