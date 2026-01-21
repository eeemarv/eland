<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumPostCommand;
use App\Form\Type\Forum\ForumPostType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\ForumRepository;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ForumEditPostController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/forum/{id}/edit-post',
    name: 'forum_edit_post',
    methods: ['GET', 'POST'],
    requirements: [
      'id'            => '%assert.id%',
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.user%',
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
      throw $this->createNotFoundException('Forum module not enabled.');
    }

    $forum_post = $forum_repository->get_post(
      post_id: $id,
      schema: $pp->schema_o(),
    );

    if ($forum_post === false)
    {
      throw $this->createNotFoundException(
        'Forum post ' . $id . ' not found.'
      );
		}

    $forum_topic = $forum_repository->get_topic(
      topic_id: $forum_post['topic_id'],
      schema: $pp->schema_o(),
    );

		if ($forum_topic === false)
		{
			throw $this->createNotFoundException(
        'Forum topic ' . $forum_post['topic_id'] . ' not found.'
      );
		}

    if (!$item_access_service->is_visible($forum_topic['access']))
    {
      throw $this->createAccessDeniedException('Access denied for forum topic ' . $forum_topic['id']);
    }

    if (!($pp->is_admin() || $su->is_owner($forum_post['user_id'])))
    {
      throw $this->createAccessDeniedException('No rights for this action.');
    }

    $first_post_id = $forum_repository->get_first_post(
      topic_id: $forum_topic['id'],
      schema: $pp->schema_o(),
    );

    if ($first_post_id === $id)
    {
      throw $this->createAccessDeniedException('Verkeerde route om eerste post aan te passen');
    }

    $command = new ForumPostCommand();
    $command->content = $forum_post['content'];

    $form_options = [
      'validation_groups' => ['edit'],
    ];

    $form = $this->createForm(ForumPostType::class, $command, $form_options);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();
      $content = $command->content;

      $forum_repository->update_post(
        post_id: $id,
        content: $content,
        schema: $pp->schema_o(),
      );

      $this->addFlash('success', 'Reactie aangepast.');

      return $this->redirectToRoute('forum_topic', [
        ...$pp->ary(),
        'id' => $forum_topic['id'],
      ]);
    }

    return $this->render('forum/forum_edit_post.html.twig', [
      'form'          => $form->createView(),
      'forum_topic'   => $forum_topic,
      'forum_post'    => $forum_post,
    ]);
  }
}
