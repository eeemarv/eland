<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Command\News\NewsCommand;
use App\Form\Type\News\NewsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\NewsRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class NewsAddController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/news/add',
    name: 'news_add',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'news',
    ],
  )]

  public function __invoke(
    Request $request,
    NewsRepository $news_repository,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'news.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('News module not enabled.');
    }

    $command = new NewsCommand();

    $form_options = [
      'validation_groups' => ['add'],
    ];

    $form = $this->createForm(NewsType::class, $command, $form_options);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();

      $subject = $command->subject;
      $content = $command->content;
      $access = $command->access;
      $location = $command->location;
      $event_at = $command->event_at;

      if (isset($event_at))
      {
        $utc = new \DateTimeZone('UTC');
        $event_at = new \DateTimeImmutable($event_at, $utc);
      }

      $id = $news_repository->insert(
        subject: $subject,
        content: $content,
        access: $access,
        location: $location,
        event_at: $event_at,
        user_id: $su->id(),
        schema: $pp->schema_o(),
      );

      $this->addFlash('success', 'Nieuwsbericht opgeslagen.');

      return $this->redirectToRoute('news_show', [
        ...$pp->ary(),
        'id' => $id,
      ]);
    }

    return $this->render('news/news_add.html.twig', [
      'form'      => $form->createView(),
    ]);
  }
}
