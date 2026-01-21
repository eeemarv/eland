<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Command\News\NewsCommand;
use App\Form\Type\News\NewsDelType;
use App\Repository\NewsRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class NewsDelController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/news/{id}/del',
    name: 'news_del',
    methods: ['GET', 'POST'],
    requirements: [
      'id'            => '%assert.id%',
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'news',
    ],
  )]

  public function __invoke(
    Request $request,
    int $id,
    NewsRepository $news_repository,
    ConfigService $config_service,
    PageParamsService $pp,
    VarRouteService $vr,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'news.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('News module not enabled.');
    }

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
      'validation_groups' => ['del'],
    ];

    $form = $this->createForm(NewsDelType::class, $command, $form_options);
    $form->handleRequest($request);

    if ($form->isSubmitted()
        && $form->isValid())
    {
      $news_repository->del(
        id: $id,
        schema: $pp->schema_o(),
      );

      $this->addFlash('success', 'Nieuwsbericht "' . $news_item['subject'] . '" verwijderd.');

      return $this->redirectToRoute($vr->get('news'), $pp->ary());
    }

    return $this->render('news/news_del.html.twig', [
      'form'      => $form->createView(),
      'news_item' => $news_item,
    ]);
  }
}
