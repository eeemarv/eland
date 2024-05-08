<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Cache\ConfigCache;
use App\Repository\NewsRepository;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class NewsShowController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/news/{id}',
        name: 'news_show',
        methods: ['GET'],
        priority: 10,
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
        ],
        defaults: [
            'module'        => 'news',
        ],
    )]

    public function __invoke(
        int $id,
        NewsRepository $news_repository,
        ConfigCache $config_cache,
        ItemAccessService $item_access_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_cache->get_bool('news.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('News module not enabled.');
        }

        $show_access = ($pp->is_user()
                && $config_cache->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        $sort_asc = $config_cache->get_bool('news.sort.asc', $pp->schema());
        $visible_ary = $item_access_service->get_visible_ary_for_page();
        $news_item = $news_repository->get_with_prev_next($id, $sort_asc, $visible_ary, $pp->schema());

        return $this->render('news/news_show.html.twig', [
            'news_item'     => $news_item,
            'show_access'   => $show_access,
            'id'            => $id,
            'prev_id'       => $news_item['prev_id'],
            'next_id'       => $news_item['next_id'],
        ]);
    }
}
