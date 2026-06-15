<?php declare(strict_types=1);

namespace App\Controller\Tags;

use App\Command\Tags\TagsListCommand;
use App\Form\Type\Tags\TagsListType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\TagRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TagsListController extends AbstractController
{
    #[Route(
    '/{schema}/{role_short}/tags/{tag_type}',
    name: 'tags',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'users',
      'sub_module'    => 'tags',
    ],
  )]

  public function __invoke(
    string $tag_type,
    Request $request,
    ConfigService $config_service,
    TagRepository $tag_repository,
    PageParamsService $pp,
  ):Response
  {
    switch ($tag_type)
    {
      case 'users':
        if (!$config_service->get_bool(
          config_id: 'users.tags.enabled',
          schema: $pp->schema_o(),
        ))
        {
          throw $this->createNotFoundException(
            'Tags for users not enabled.'
          );
        }
        break;
      default:
        throw $this->createNotFoundException(
          'Tag type not supported.'
        );
        break;
    }

    $command = new TagsListCommand();

    $tags = $tag_repository->get_all_with_count(
      tag_type: $tag_type,
      schema: $pp->schema_o(),
    );

    $tag_id_ary = [];

    foreach ($tag_id_ary as $tag)
    {
      $tag_id_ary[] = $tag['id'];
    }

    $command->tags = implode(',', $tag_id_ary);

    $form = $this->createForm(
      type:TagsListType::class,
      data: $command,
    );

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();
      $posted_tag_id_ary = explode(',', $command->tags);
      $tags_list = [];

      foreach ($posted_tag_id_ary as $tag_id)
      {
        $tags_list[] = (int) $tag_id;
      }

      $update_count = $tag_repository->update_list(
        tags_list: $tags_list,
        tag_type: $tag_type,
        schema: $pp->schema_o(),
      );

      if ($update_count)
      {
        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'tags.list.flash.success',
          ],
        );
      }
      else
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
          ],
        );
      }

      return $this->redirectToRoute('tags', [
        'tag_type'  => $tag_type,
        ...$pp->ary(),
      ]);
    }

    return $this->render('tags/tags_list.html.twig', [
      'tags'      => $tags,
      'form'      => $form->createView(),
      'module'    => $tag_type,
      'tag_type'  => $tag_type,
    ]);
  }
}
