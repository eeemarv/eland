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
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsController]
class TagsDelController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: UsersSubModule::Tags,
    path: '/users/tags/{id}/del',
    name: 'users_tags_del',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    int $id,
    string $_module,
    TagRepository $tag_repository,
    PageParamsService $pp,
  ):Response
  {
    $tag = $tag_repository->get_with_count(
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

    if ($tag['count'] !== 0)
    {
      throw new BadRequestHttpException(
        'Count not zero, tag can not be deleted.'
      );
    }

    $command = new TagsDefCommand();
    $command->txt = $tag['txt'];
    $command->txt_color = $tag['txt_color'];
    $command->bg_color = $tag['bg_color'];
    $command->description = $tag['description'];
    $command->id = $id;
    $command->tag_type = $_module;

    $form = $this->createForm(TagsDefType::class, $command, [
      'del'       => true,
      'tag_type'  => $_module,
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {

      $tag_repository->del(
        id: $id,
        tag_type: $_module,
        schema: $pp->schema_o(),
      );

      $tag = $this->renderView('component/tag.html.twig', [
        'tag' => $command,
      ]);

      $this->addFlash(
        type:'success',
        message: [
          'key' => 'tags.del.flash.success',
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

    return $this->render('tags/tags_del.html.twig', [
      'form'      => $form->createView(),
      'tag_type'  => $_module,
    ]);
  }
}
