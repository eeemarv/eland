<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Routing\Annotation\Route;

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
        Db $db,
        AccountRender $account_render,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        ItemAccessService $item_access_service,
        MenuService $menu_service,
        LinkRender $link_render,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('news.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('News module not enabled.');
        }

        $show_access = ($pp->is_user()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        $news = NewsListController::get_data(
            $db,
            $config_service,
            $item_access_service,
            $pp
        );

        if (!isset($news[$id]))
        {
            throw new NotFoundHttpException('Dit nieuwsbericht bestaat niet of je hebt er geen toegang toe.');
        }

        $news_item = $news[$id];

        $next_id = $prev_id = $current_news = false;

        foreach($news as $nid => $ndata)
        {
            if ($current_news)
            {
                $next_id = $nid;
                break;
            }

            if ($id == $nid)
            {
                $current_news = true;
                continue;
            }

            $prev_id = $nid;
        }

        $out = NewsExtendedController::render_news_item(
            $news_item,
            $show_access,
            false,
            false,
            $pp,
            $link_render,
            $account_render,
            $date_format_service,
            $item_access_service
        );

        $menu_service->set('news');

        return $this->render('news/news_show.html.twig', [
            'content'   => $out,
            'news_item' => $news_item,
            'id'        => $id,
            'prev_id'   => $prev_id,
            'next_id'   => $next_id,
        ]);
    }
}
