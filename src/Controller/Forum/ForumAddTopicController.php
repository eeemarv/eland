<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumTopicCommand;
use App\Form\Type\Forum\ForumTopicType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\ForumRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ForumAddTopicController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/forum/add-topic',
    name: 'forum_add_topic',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.user%',
    ],
    defaults: [
      'module'        => 'forum',
    ],
  )]

  public function __invoke(
    Request $request,
    ForumRepository $forum_repository,
    ConfigService $config_service,
    SessionUserService $su,
    PageParamsService $pp
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'forum.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('Forum module not enabled.');
    }

    $command = new ForumTopicCommand();

    $form_options = [
      'validation_groups' => ['add'],
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

      $id = $forum_repository->insert_topic(
        subject: $subject,
        content: $content,
        access: $access,
        user_id: $su->id(),
        schema: $pp->schema_o(),
      );

      $this->addFlash('success', 'Forum onderwerp toegevoegd.');

      return $this->redirectToRoute('forum_topic', [
        ...$pp->ary(),
        'id' => $id,
      ]);
    }

    return $this->render('forum/forum_add_topic.html.twig', [
      'form'      => $form->createView(),
    ]);
  }
}
