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
class NewsListController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/news',
    name: 'news_list',
    methods: ['GET'],
    priority: 20,
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.guest%',
    ],
    defaults: [
      'module'        => 'news',
    ],
  )]

  public function __invoke(
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

    $news_items = $news_repository->get_all(
      event_at_asc_en: $sort_asc,
      visible_ary: $visible_ary,
      schema: $pp->schema_o(),
    );

    return $this->render('news/news_list.html.twig', [
      'news_items'    => $news_items,
      'show_access'   => $show_access,
    ]);
  }
}
