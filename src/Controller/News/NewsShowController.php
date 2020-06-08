<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Repository\NewsRepository;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class NewsShowController extends AbstractController
{
    public function __invoke(
        int $id,
        NewsRepository $news_repository,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        ItemAccessService $item_access_service,
        MenuService $menu_service,
        PageParamsService $pp,
        VarRouteService $vr
    ):Response
    {
        $visible_ary = $item_access_service->get_visible_ary_for_page();
        $event_at_asc_en = $config_service->get('news_order_asc', $pp->schema()) === '1' ? true : false;

        $show_access = ($pp->is_user()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        $news_item = $news_repository->get($id, $pp->schema());

        if (!$item_access_service->is_visible($news_item['access']))
        {
            throw new AccessDeniedHttpException('No access to news item ' . $id);
        }

        $pr_ne_ary = $news_repository->get_prev_and_next_id($id, $event_at_asc_en, $visible_ary, $pp->schema());

        if($pp->is_admin())
        {
            $btn_top_render->edit('news_edit', $pp->ary(),
                ['id' => $id], 'Nieuwsbericht aanpassen');

            $btn_top_render->del('news_del', $pp->ary(),
                ['id' => $id], 'Nieuwsbericht verwijderen');
        }

        $prev_ary = $pr_ne_ary['prev_id'] ? ['id' => $pr_ne_ary['prev_id']] : [];
        $next_ary = $pr_ne_ary['next_id'] ? ['id' => $pr_ne_ary['next_id']] : [];

        $btn_nav_render->nav('news_show', $pp->ary(),
            $prev_ary, $next_ary, false);

        $btn_nav_render->nav_list($vr->get('news'), $pp->ary(),
            [], 'Lijst', 'calendar-o');

        $menu_service->set('news');

        return $this->render('news/news_show.html.twig', [
            'news_item'     => $news_item,
            'show_access'   => $show_access,
            'schema'        => $pp->schema(),
        ]);
    }
}
