<?php declare(strict_types=1);

namespace App\Controller\UniqueCheck;

use App\Repository\TagRepository;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UniqueCheckTagsTxtController extends AbstractController
{
  use EtagJsonResponseTrait;

  #[Route(
    '/{schema}/{role_short}/unique-check/tags-txt/{tag_type}',
    name: 'unique_check_tags_txt',
    methods: ['GET'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
      'tag_type'      => '%assert.tag_type%',
    ],
  )]

  public function __invoke(
    string $tag_type,
    TagRepository $tag_repository,
    Request $request,
    PageParamsService $pp,
  ):Response
  {
    $tags = $tag_repository->get_flat_ary(
      tag_type: $tag_type,
      schema: $pp->schema_o(),
    );

    return $this->etagJsonResponse($request, $tags);
  }
}
