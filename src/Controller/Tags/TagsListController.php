<?php declare(strict_types=1);

namespace App\Controller\Tags;

use App\Command\Tags\TagsListCommand;
use App\Form\Type\Tags\TagsListType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\TagRepository;
use App\Routing\AccessLevel;
use App\Routing\SchemaRoute;
use App\Routing\UsersSubModule;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class TagsListController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: UsersSubModule::Tags,
    path: '/users/tags',
    name: 'users_tags',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    string $_module,
    Request $request,
    TagRepository $tag_repository,
    PageParamsService $pp,
  ):Response
  {
    $command = new TagsListCommand();

    $tags = $tag_repository->get_all_with_count(
      tag_type: $_module,
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
      $posted_tag_id_ary = explode(',', $command->tags);
      $tags_list = [];

      foreach ($posted_tag_id_ary as $tag_id)
      {
        $tags_list[] = (int) $tag_id;
      }

      $update_count = $tag_repository->update_list(
        tags_list: $tags_list,
        tag_type: $_module,
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

      return $this->redirectToRoute(
        route: $_module . '_tags',
        parameters: $pp->ary(),
      );
    }

    return $this->render('tags/tags_list.html.twig', [
      'tags'      => $tags,
      'form'      => $form->createView(),
      'tag_type'  => $_module,
    ]);
  }
}
