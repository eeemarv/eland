<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Command\News\NewsCommand;
use App\Form\Type\News\NewsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\NewsRepository;
use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class NewsEditController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: AppModule::News,
    path: '/news/{id}/edit',
    name: 'news_edit',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    int $id,
    NewsRepository $news_repository,
    PageParamsService $pp,
  ):Response
  {

    $news_item = $news_repository->get(
      id: $id,
      schema: $pp->schema_o(),
    );

    if ($news_item === false)
    {
      throw $this->createNotFoundException(
        'News item with id ' . $id . ' not found'
      );
    }

    $command = new NewsCommand();
    $command->id = $id;
    $command->subject = $news_item['subject'];
    $command->event_at = $news_item['event_at'];
    $command->location = $news_item['location'];
    $command->content = $news_item['content'];
    $command->access = $news_item['access'];

    $form_options = [
      'validation_groups' => ['edit'],
    ];

    $form = $this->createForm(
      type: NewsType::class,
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
      $location = $command->location;
      $event_at = $command->event_at;

      if (isset($event_at))
      {
        $utc = new \DateTimeZone('UTC');
        $event_at = new \DateTimeImmutable($event_at, $utc);
      }

      $news_repository->update(
        id: $id,
        subject: $subject,
        content: $content,
        access: $access,
        location: $location,
        event_at: $event_at,
        schema: $pp->schema_o(),
      );

      $this->addFlash('success', 'Nieuwsbericht aangepast.');

      return $this->redirectToRoute('news_show', [
        ...$pp->ary(),
        'id' => $id,
      ]);
    }

    return $this->render('news/news_edit.html.twig', [
      'form'      => $form->createView(),
      'news_item' => $news_item,
    ]);
  }
}
