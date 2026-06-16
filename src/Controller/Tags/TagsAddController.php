<?php declare(strict_types=1);

namespace App\Controller\Tags;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Command\Tags\TagsDefCommand;
use App\Form\Type\Tags\TagsDefType;
use App\Repository\TagRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TagsAddController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/tags/{tag_type}/add',
    name: 'tags_add',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
      'tag_type'      => '%assert.tag_type%',
    ],
    defaults: [
      'sub_module'    => 'tags',
    ],
  )]

  public function __invoke(
    string $tag_type,
    Request $request,
    TagRepository $tag_repository,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
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

    $command = new TagsDefCommand();
    $command->id = 0;
    $command->tag_type = $tag_type;
    $command->bg_color = '#eeeeee';
    $command->txt_color = '#555555';
    $command->txt = null;
    $command->description = null;

    $form = $this->createForm(
      type: TagsDefType::class,
      data: $command,
      options: [
        'tag_type'  => $tag_type,
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
        tag_type: $tag_type,
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

      return $this->redirectToRoute('tags', [
        ...$pp->ary(),
        'tag_type'  => $tag_type,
      ]);
    }

    return $this->render('tags/tags_add.html.twig', [
      'form'      => $form->createView(),
      'module'    => $tag_type,
      'tag_type'  => $tag_type,
    ]);
  }
}
