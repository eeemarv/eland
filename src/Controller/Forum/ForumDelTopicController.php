<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumTopicCommand;
use App\Form\Type\Forum\ForumTopicDelType;
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
class ForumDelTopicController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/forum/{id}/del-topic',
    name: 'forum_del_topic',
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
    ItemAccessService $item_access_service,
    AccountRender $account_render,
    ConfigService $config_service,
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

    $command = new ForumTopicCommand();

    $command->subject = $forum_topic['subject'];
    $command->content = $forum_post['content'];
    $command->access = $forum_topic['access'];

    $form_options = [
      'validation_groups'     => 'del',
    ];

    $form = $this->createForm(ForumTopicDelType::class, $command, $form_options);

    $form->handleRequest($request);

    if ($form->isSubmitted()
        && $form->isValid())
    {
      $forum_repository->del_topic(
        topic_id: $id,
        schema: $pp->schema_o(),
      );

      $topic_subject = $forum_topic['subject'];
      $account_str = $account_render->str($forum_topic['user_id'], $pp->schema());

      if ($su->is_owner($forum_topic['user_id']))
      {
        $this->addFlash('success', 'Je forum onderwerp "' . $topic_subject . '" is verwijderd.');
      }
      else
      {
        $this->addFlash('success', 'Het forum onderwerp "' . $topic_subject . '" van ' . $account_str . ' is verwijderd.');
      }

      return $this->redirectToRoute('forum', $pp->ary());
    }

    return $this->render('forum/forum_del_topic.html.twig', [
      'form'              => $form->createView(),
      'forum_topic'       => $forum_topic,
    ]);
  }
}
