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
class NewsExtendedController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/news/extended',
        name: 'news_extended',
        methods: ['GET'],
        priority: 20,
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
        ],
        defaults: [
            'module'        => 'news',
        ],
    )]

    public function __invoke(
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
        $news_items = $news_repository->get_all($sort_asc, $visible_ary, $pp->schema());

        return $this->render('news/news_extended.html.twig', [
            'news_items'    => $news_items,
            'show_access'   => $show_access,
        ]);
    }
}
