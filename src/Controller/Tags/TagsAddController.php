<?php declare(strict_types=1);

namespace App\Controller\Tags;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Command\Tags\TagsDefCommand;
use App\Form\Type\Tags\TagsDefType;
use App\Repository\TagRepository;
use App\Routing\AccessLevel;
use App\Routing\SchemaRoute;
use App\Routing\UsersSubModule;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class TagsAddController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: UsersSubModule::Tags,
    path: 'users/tags/add',
    name: 'users_tags_add',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    string $_module,
    Request $request,
    TagRepository $tag_repository,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {

    $command = new TagsDefCommand();
    $command->id = 0;
    $command->tag_type = $_module;
    $command->bg_color = '#eeeeee';
    $command->txt_color = '#555555';
    $command->txt = null;
    $command->description = null;

    $form = $this->createForm(
      type: TagsDefType::class,
      data: $command,
      options: [
        'tag_type'  => $_module,
      ],
    );

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $txt = $command->txt;
      $description = $command->description;
      $txt_color = $command->txt_color;
      $bg_color = $command->bg_color;

      $tag_repository->insert(
        tag_type: $_module,
        txt: (string) $txt,
        txt_color: $txt_color,
        bg_color: $bg_color,
        description: $description,
        created_by: $su->id() ?: null,
        schema: $pp->schema_o(),
      );

      $tag = $this->renderView('component/tag.html.twig', [
        'tag' => $command,
      ]);

      $this->addFlash(
        type:'success',
        message: [
          'key' => 'tags.add.flash.success',
          'params'  => [
            'tag' => $tag,
          ],
          'is_raw' => true,
        ],
      );

      return $this->redirectToRoute(
        route: $_module . '_tags', 
        parameters: $pp->ary(),
      );
    }

    return $this->render('tags/tags_add.html.twig', [
      'form'      => $form->createView(),
      'tag_type'  => $_module,
    ]);
  }
}
