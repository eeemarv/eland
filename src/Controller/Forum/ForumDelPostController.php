<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumPostCommand;
use App\Form\Type\Forum\ForumPostDelType;
use App\Render\AccountRender;
use App\Repository\ForumRepository;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ForumDelPostController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/forum/{id}/del-post',
    name: 'forum_del_post',
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
    AccountRender $account_render,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
    ItemAccessService $item_access_service,
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
      throw $this->createAccessDeniedException('Access denied for forum topic ' . $forum_topic['id'] . '.');
    }

    if (!($su->is_owner($forum_post['user_id']) || $pp->is_admin()))
    {
      throw $this->createAccessDeniedException('No rights for this action.');
    }

    $first_post_id = $forum_repository->get_first_post_id(
      topic_id: $forum_topic['id'],
      schema: $pp->schema_o(),
    );

    if ($first_post_id === $id)
    {
      throw $this->createAccessDeniedException('Wrong route for this action.');
    }

    $command = new ForumPostCommand();
    $command->content = $forum_post['content'];

    $form = $this->createForm(ForumPostDelType::class, $command);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $forum_repository->del_post(
        post_id: $id,
        schema: $pp->schema_o(),
      );

      $topic_subject = $forum_topic['subject'];
      $account_str = $account_render->str($forum_post['user_id'], $pp->schema());

      $alert_msg = $su->is_owner($forum_post['user_id']) ? 'Je post' : 'De post van ' . $account_str;
      $alert_msg .= ' in topic "' . $topic_subject . '" werd gewist.';

      $this->addFlash('success', $alert_msg);

      return $this->redirectToRoute('forum_topic', [
        ...$pp->ary(),
        'id' => $forum_topic['id'],
      ]);
    }

    return $this->render('forum/forum_del_post.html.twig', [
      'form'          => $form->createView(),
      'forum_topic'   => $forum_topic,
      'forum_post'    => $forum_post,
    ]);
  }
}
