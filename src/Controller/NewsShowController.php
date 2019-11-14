<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
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
        PageParamsService $pp,
        VarRouteService $vr
    ):Response
    {
        $show_visibility = ($pp->is_user()
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

            if (!$news_item['approved'])
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

        $heading_render->add('Nieuwsbericht: ' . $news_item['headline']);
        $heading_render->fa('calendar-o');

        $out = '<div class="panel panel-default printview">';
        $out .= '<div class="panel-body';
        $out .= $news_item['approved'] ? '' : ' bg-inactive';
        $out .= '">';

        $out .= '<dl>';

        if ($pp->is_admin())
        {
            $out .= '<dt>Goedgekeurd en gepubliceerd door Admin</dt>';
            $out .= '<dd>';
            $out .= $news_item['approved'] ? 'Ja' : 'Nee';
            $out .= '</dd>';
        }

        $out .= '<dt>Agendadatum</dt>';

        $out .= '<dd>';

        if ($news_item['itemdate'])
        {
            $out .= $date_format_service->get($news_item['itemdate'], 'day', $pp->schema());
        }
        else
        {
            $out .= '<i class="fa fa-times"></i>';
        }

        $out .= '</dd>';

        $out .= '<dt>Behoud na datum?</dt>';
        $out .= '<dd>';
        $out .= $news_item['sticky'] ? 'Ja' : 'Nee';
        $out .= '</dd>';

        $out .= '<dt>Locatie</dt>';
        $out .= '<dd>';

        if ($news_item['location'])
        {
            $out .= htmlspecialchars($news_item['location'], ENT_QUOTES);
        }
        else
        {
            $out .= '<i class="fa fa-times"></i>';
        }

        $out .= '</dd>';

        $out .= '<dt>Bericht/Details</dt>';
        $out .= '<dd>';
        $out .= nl2br(htmlspecialchars($news_item['newsitem'],ENT_QUOTES));
        $out .= '</dd>';

        if ($show_visibility)
        {
            $out .= '<dt>Zichtbaarheid</dt>';
            $out .= '<dd>';
            $out .= $item_access_service->get_label($news_item['access']);
            $out .= '</dd>';
        }

        $out .= '<dt>Ingegeven door</dt>';
        $out .= '<dd>';
        $out .= $account_render->link($news_item['id_user'], $pp->ary());
        $out .= '</dd>';

        $out .= '</dl>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('news');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
