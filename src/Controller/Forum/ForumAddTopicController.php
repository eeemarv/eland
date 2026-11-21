<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumTopicCommand;
use App\Form\Type\Forum\ForumTopicType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\ForumRepository;
use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class ForumAddTopicController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::User,
    module: AppModule::Forum,
    path: '/forum/add-topic',
    name: 'forum_add_topic',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    ForumRepository $forum_repository,
    SessionUserService $su,
    PageParamsService $pp
  ):Response
  {

    $command = new ForumTopicCommand();

    $form_options = [
      'validation_groups' => ['add'],
    ];

    $form = $this->createForm(
      type:ForumTopicType::class,
      data: $command,
      options: $form_options,
    );
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
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
