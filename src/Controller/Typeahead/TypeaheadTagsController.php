<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Repository\TagRepository;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TypeaheadTagsController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/typeahead-tags/{tag_type}/{thumbprint}',
    name: 'typeahead_tags',
    methods: ['GET'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.guest%',
      'thumbprint'    => '%assert.thumbprint%',
      'tag_type'      => '%assert.tag_type%',
    ],
  )]

  public function __invoke(
    string $thumbprint,
    string $tag_type,
    TagRepository $tag_repository,
    TypeaheadService $typeahead_service,
    PageParamsService $pp,
  ):Response
  {
    $cached = $typeahead_service->get_cached_data($thumbprint, $pp, []);

    if ($cached !== false)
    {
      return new Response($cached, 200, ['Content-Type' => 'application/json']);
    }

    $tags = $tag_repository->get_all_for_render(
      tag_type: $tag_type,
      schema: $pp->schema_o(),
    );

    $data = json_encode($tags);
    $typeahead_service->set_thumbprint($thumbprint, $data, $pp, []);
    return new Response($data, 200, ['Content-Type' => 'application/json']);
  }
}
