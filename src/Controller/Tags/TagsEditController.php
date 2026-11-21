<?php declare(strict_types=1);

namespace App\Controller\Tags;

use App\Command\Tags\TagsDefCommand;
use App\Form\Type\Tags\TagsDefType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\TagRepository;
use App\Routing\AccessLevel;
use App\Routing\SchemaRoute;
use App\Routing\UsersSubModule;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class TagsEditController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: UsersSubModule::Tags,
    path: '/users/tags/{id}/edit',
    name: 'users_tags_edit',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    int $id,
    string $_module,
    ConfigService $config_service,
    TagRepository $tag_repository,
    PageParamsService $pp,
  ):Response
  {

    $tag = $tag_repository->get(
      id: $id,
      tag_type: $_module,
      schema: $pp->schema_o(),
    );

    if ($tag === false)
    {
      throw $this->createNotFoundException(
        'Tag with id ' . $id . ' not found'
      );
    }

    $command = new TagsDefCommand();
    $command->id = $id;
    $command->tag_type = $_module;
    $command->txt = $tag['txt'];
    $command->txt_color = $tag['txt_color'];
    $command->bg_color = $tag['bg_color'];
    $command->description = $tag['description'];

    $form = $this->createForm(
      type: TagsDefType::class,
      data: $command,
      options: [
        'tag_type'  => $_module,
        'txt_omit'  => $tag['txt'],
      ],
    );
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $txt = $command->txt;
      $txt_color = $command->txt_color;
      $bg_color = $command->bg_color;
      $description = $command->description;

      $changed = $txt !== $tag['txt']
        || $txt_color !== $tag['txt_color']
        || $bg_color !== $tag['bg_color']
        || $description !== $tag['description'];

      if ($changed)
      {
        $tag_repository->update(
          id: $id,
          tag_type: $_module,
          txt: $txt,
          txt_color: $txt_color,
          bg_color: $bg_color,
          description: $description,
          schema: $pp->schema_o(),
        );

        $tag = $this->renderView('component/tag.html.twig', [
          'tag' => $command,
        ]);

        $this->addFlash(
          type:'success',
          message: [
            'key' => 'tags.edit.flash.success',
            'params'  => [
              'tag' => $tag,
            ],
            'is_raw' => true,
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

    return $this->render('tags/tags_edit.html.twig', [
      'form'      => $form->createView(),
      'tag_type'  => $_module,
    ]);
  }
}
