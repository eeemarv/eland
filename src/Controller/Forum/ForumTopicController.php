<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumPostCommand;
use App\Form\Type\Forum\ForumPostType;
use App\Repository\ForumRepository;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ForumTopicController extends AbstractController
{
  #[Route(
    '/{system}/{role_short}/forum/{id}',
    name: 'forum_topic',
    methods: ['GET', 'POST'],
    priority: 10,
    requirements: [
      'id'            => '%assert.id%',
      'system'        => '%assert.system%',
      'role_short'    => '%assert.role_short.guest%',
    ],
    defaults: [
      'module'        => 'forum',
    ],
  )]

  public function __invoke(
    Request $request,
    int $id,
    ForumRepository $forum_repository,
    ConfigService $config_service,
    ItemAccessService $item_access_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'forum.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw new NotFoundHttpException('Forum module not enabled.');
    }

    $visible_ary = $item_access_service->get_visible_ary_for_page();
    $topic = $forum_repository->get_topic_with_prev_next(
      topic_id: $id,
      visible_ary: $visible_ary,
      schema: $pp->schema_o(),
    );

    $command = new ForumPostCommand();

    $form_options = [
      'validation_groups' => ['add'],
    ];

    $form = $this->createForm(ForumPostType::class, $command, $form_options);

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();
      $forum_repository->insert_post(
        command: $command,
        user_id: $su->id(),
        topic_id: $id,
        schema: $pp->schema_o(),
      );

      $this->addFlash('success', 'Reactie toegevoegd.');

      return $this->redirectToRoute('forum_topic', [
        ...$pp->ary(),
        'id' => $id,
      ]);
    }

    $posts = $forum_repository->get_topic_posts(
      topic_id: $id,
      schema: $pp->schema_o(),
    );

    $show_access = ($pp->is_user()
      && $config_service->get_intersystem_en(
        schema: $pp->schema_o()))
      || $pp->is_admin();

    return $this->render('forum/forum_topic.html.twig', [
      'topic'         => $topic,
      'show_access'   => $show_access,
      'posts'         => $posts,
      'id'            => $id,
      'prev_id'       => $topic['prev_id'],
      'next_id'       => $topic['next_id'],
      'form'          => $form->createView(),
    ]);
  }
}
