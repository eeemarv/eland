<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumTopicCommand;
use App\Form\Type\Forum\ForumTopicType;
use App\Render\AccountRender;
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
class ForumEditTopicController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/forum/{id}/edit-topic',
    name: 'forum_edit_topic',
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
    ItemAccessService $item_access_service,
    PageParamsService $pp,
    SessionUserService $su
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'forum.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('Forum module not enabled.');
    }

    $forum_topic = $forum_repository->get_topic(
      topic_id: $id,
      schema: $pp->schema_o(),
    );

		if ($forum_topic === false)
		{
			throw $this->createNotFoundException(
        'Forum topic ' . $id . ' not found.'
      );
		}

    if (!$item_access_service->is_visible($forum_topic['access']))
    {
      throw $this->createAccessDeniedException('Access denied (1) for forum topic with id ' . $id);
    }

    if (!($su->is_owner($forum_topic['user_id']) || $pp->is_admin()))
    {
      throw $this->createAccessDeniedException('Access Denied (2) for forum topic with id ' . $id);
    }

    $forum_post = $forum_repository->get_first_post(
      topic_id: $id,
      schema: $pp->schema_o(),
    );

    if (!($su->is_owner($forum_post['user_id']) || $pp->is_admin()))
    {
      throw $this->createAccessDeniedException('Access denied (3) forum forum post');
    }

    $command = new ForumTopicCommand;

    $command->subject = $forum_topic['subject'];
    $command->content = $forum_post['content'];
    $command->access = $forum_topic['access'];

    $form_options = [
      'validation_groups' => ['edit'],
    ];

    $form = $this->createForm(ForumTopicType::class, $command, $form_options);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();
      $subject = $command->subject;
      $content = $command->content;
      $access = $command->access;

      $forum_repository->update_topic(
        topic_id: $id,
        subject: $subject,
        content: $content,
        access: $access,
        schema: $pp->schema_o(),
      );

      if ($su->is_owner($forum_topic['user_id']))
      {
        $this->addFlash('success', 'Je forum onderwerp is aangepast.');
      }
      else
      {
        $this->addFlash('success', 'Forum onderwerp van ' .
          $account_render->get_str($forum_topic['user_id'], $pp->schema()) .
          ' aangepast.');
      }

      return $this->redirectToRoute('forum_topic', [
        ...$pp->ary(),
        'id' => $id,
      ]);
    }

    return $this->render('forum/forum_edit_topic.html.twig', [
      'form'          => $form->createView(),
      'forum_topic'   => $forum_topic,
    ]);
  }
}
