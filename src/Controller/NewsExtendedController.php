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
use App\Service\XdbService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class NewsExtendedController extends AbstractController
{
    public function __invoke(
        Db $db,
        XdbService $xdb_service,
        ConfigService $config_service,
        ItemAccessService $item_access_service,
        HeadingRender $heading_render,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        AccountRender $account_render,
        DateFormatService $date_format_service,
        LinkRender $link_render,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        $news = NewsListController::get_data(
            $db,
            $xdb_service,
            $config_service,
            $item_access_service,
            $pp
        );

        NewsListController::set_heading_and_btns(
            false,
            $heading_render,
            $btn_top_render,
            $btn_nav_render,
            $pp
        );

        $show_visibility = ($pp->is_user()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        if (!count($news))
        {
            return NewsListController::no_news($menu_service, $pp);
        }

        $out = '';

        foreach ($news as $n)
        {
            $out .=  '<div class="panel panel-info printview">';
            $out .=  '<div class="panel-body';
            $out .=  $n['approved'] ? '' : ' bg-inactive';
            $out .=  '">';

            $out .=  '<div class="media">';
            $out .=  '<div class="media-body">';
            $out .=  '<h2 class="media-heading">';

            $out .=  $link_render->link_no_attr('news_show', $pp->ary(),
                ['id' => $n['id']], $n['headline']);

            $out .=  '</h2>';

            if (!$n['approved'])
            {
                $out .=  '<p class="text-warning">';
                $out .=  '<strong>';
                $out .=  'Dit nieuwsbericht wacht op goedkeuring en publicatie door een admin';
                $out .=  '</strong>';
                $out .=  '</p>';
            }

            $out .=  '<dl>';

            $out .=  '<dt>';
            $out .=  'Agendadatum';
            $out .=  '</dt>';
            $out .=  '<dd>';

            if ($n['itemdate'])
            {
                $out .=  $date_format_service->get($n['itemdate'], 'day', $pp->schema());

                $out .=  '<br><i>';

                if ($n['sticky'])
                {
                    $out .=  'Dit nieuwsbericht blijft behouden na deze datum.';
                }
                else
                {
                    $out .=  'Dit nieuwsbericht wordt automatisch gewist na deze datum.';
                }

                $out .=  '</i>';

            }
            else
            {
                $out .=  '<i class="fa fa-times></i>';
            }

            $out .=  '</dd>';

            $out .=  '<dt>';
            $out .=  'Locatie';
            $out .=  '</dt>';
            $out .=  '<dd>';

            if ($n['location'])
            {
                $out .=  htmlspecialchars($n['location'], ENT_QUOTES);
            }
            else
            {
                $out .=  '<i class="fa fa-times"></i>';
            }

            $out .=  '</dd>';

            $out .=  '</dl>';

            $out .=  '<h4>Bericht/Details</h4>';
            $out .=  '<p>';
            $out .=  nl2br(htmlspecialchars($n['newsitem'],ENT_QUOTES));
            $out .=  '</p>';

            $out .=  '<dl>';

            if ($show_visibility)
            {
                $out .=  '<dt>';
                $out .=  'Zichtbaarheid';
                $out .=  '</dt>';
                $out .=  '<dd>';
                $out .=  $item_access_service->get_label_xdb($n['access']);
                $out .=  '</dd>';
            }

            $out .=  '</dl>';

            $out .=  '</div>';
            $out .=  '</div>';
            $out .=  '</div>';

            $out .=  '<div class="panel-footer">';
            $out .=  '<p><i class="fa fa-user"></i> ';

            $out .=  $account_render->link($n['id_user'], $pp->ary());

            if ($pp->is_admin())
            {
                $out .=  '<span class="inline-buttons pull-right hidden-xs">';

                if (!$n['approved'])
                {
                    $out .=  $link_render->link_fa('news_approve', $pp->ary(),
                        ['id' => $n['id']], 'Goedkeuren en publiceren',
                        ['class' => 'btn btn-warning'], 'check');
                }

                $out .=  $link_render->link_fa('news_edit', $pp->ary(),
                    ['id' => $n['id']], 'Aanpassen',
                    ['class' => 'btn btn-primary'], 'pencil');

                $out .=  $link_render->link_fa('news_del', $pp->ary(),
                    ['id' => $n['id']], 'Verwijderen',
                    ['class' => 'btn btn-danger'], 'times');

                $out .=  '</span>';
            }

            $out .=  '</p>';
            $out .=  '</div>';
            $out .=  '</div>';
        }

        $menu_service->set('news');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
