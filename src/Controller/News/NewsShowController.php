<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Repository\NewsRepository;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class NewsShowController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/news/{id}',
    name: 'news_show',
    methods: ['GET'],
    priority: 10,
    requirements: [
      'id'            => '%assert.id%',
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.guest%',
    ],
    defaults: [
      'module'        => 'news',
    ],
  )]

  public function __invoke(
    int $id,
    NewsRepository $news_repository,
    ConfigService $config_service,
    ItemAccessService $item_access_service,
    PageParamsService $pp,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'news.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('News module not enabled.');
    }

    $show_access = ($pp->is_user()
      && $config_service->get_intersystem_en(
        schema: $pp->schema_o(),
      ))
      || $pp->is_admin();

    $sort_asc = $config_service->get_bool(
      config_id: 'news.sort.asc',
      schema: $pp->schema_o(),
    );

    $visible_ary = $item_access_service->get_visible_ary_for_page();

    $news_item = $news_repository->get_with_prev_next(
      id: $id,
      sort_event_at_asc: $sort_asc,
      visible_ary: $visible_ary,
      schema: $pp->schema_o(),
    );

		if ($news_item === false)
		{
			throw $this->createNotFoundException(
        'News item with id ' . $id . ' not found'
      );
		}

    return $this->render('news/news_show.html.twig', [
      'news_item'     => $news_item,
      'show_access'   => $show_access,
      'id'            => $id,
      'prev_id'       => $news_item['prev_id'],
      'next_id'       => $news_item['next_id'],
    ]);
  }
}
