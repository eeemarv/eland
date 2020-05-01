<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\DBAL\Connection as Db;

class NewsShowController extends AbstractController
{
    public function __invoke(
        int $id,
        Db $db,
        AccountRender $account_render,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        MenuService $menu_service,
        LinkRender $link_render,
        PageParamsService $pp,
        VarRouteService $vr
    ):Response
    {
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

        $next = $prev = $current_news = false;

        foreach($news as $nid => $ndata)
        {
            if ($current_news)
            {
                $next = $nid;
                break;
            }

            if ($id == $nid)
            {
                $current_news = true;
                continue;
            }

            $prev = $nid;
        }

        if($pp->is_admin())
        {
            $btn_top_render->edit('news_edit', $pp->ary(),
                ['id' => $id], 'Nieuwsbericht aanpassen');

            $btn_top_render->del('news_del', $pp->ary(),
                ['id' => $id], 'Nieuwsbericht verwijderen');

            if (!$news_item['is_approved'])
            {
                $btn_top_render->approve('news_approve', $pp->ary(),
                    ['id' => $id], 'Nieuwsbericht goedkeuren en publiceren');
            }
        }

        $prev_ary = $prev ? ['id' => $prev] : [];
        $next_ary = $next ? ['id' => $next] : [];

        $btn_nav_render->nav('news_show', $pp->ary(),
            $prev_ary, $next_ary, false);

        $btn_nav_render->nav_list($vr->get('news'), $pp->ary(),
            [], 'Lijst', 'calendar-o');

        $heading_render->add('Nieuwsbericht: ' . $news_item['subject']);
        $heading_render->fa('calendar-o');

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

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
