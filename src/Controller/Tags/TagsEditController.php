<?php declare(strict_types=1);

namespace App\Controller\Tags;

use App\Command\Tags\TagsDefCommand;
use App\Form\Type\Tags\TagsDefType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\TagRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TagsEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/tags/{tag_type}/{id}/edit',
    name: 'tags_edit',
    methods: ['GET', 'POST'],
    requirements: [
      'id'            => '%assert.id%',
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
      'tag_type'      => '%assert.tag_type%',
    ],
    defaults: [
      'sub_module'    => 'tags',
    ],
  )]

  public function __invoke(
    Request $request,
    int $id,
    string $tag_type,
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

    $tag = $tag_repository->get(
      id: $id,
      tag_type: $tag_type,
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
    $command->tag_type = $tag_type;
    $command->txt = $tag['txt'];
    $command->txt_color = $tag['txt_color'];
    $command->bg_color = $tag['bg_color'];
    $command->description = $tag['description'];

    $form = $this->createForm(TagsDefType::class, $command, [
      'tag_type'  => $tag_type,
      'txt_omit'  => $tag['txt'],
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();

      $txt = $command->txt;
      $txt_color = $command->txt_color;
      $bg_color = $command->bg_color;
      $description = $command->description;

      $tag_repository->update(
        id: $id,
        tag_type: $tag_type,
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

      return $this->redirectToRoute('tags', [
        ...$pp->ary(),
        'tag_type'  => $tag_type,
      ]);
    }

    return $this->render('tags/tags_edit.html.twig', [
      'form'      => $form->createView(),
      'module'    => $tag_type,
      'tag_type'  => $tag_type,
    ]);
  }
}
