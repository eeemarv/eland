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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class NewsExtendedController extends AbstractController
{
    public function __invoke(
        Db $db,
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

        $show_access = ($pp->is_user()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        if (!count($news))
        {
            $content = NewsListController::no_news($menu_service, $pp);

            return $this->render('base/navbar.html.twig', [
                'content'   => $content,
                'schema'    => $pp->schema(),
            ]);
        }

        $out = '';

        foreach ($news as $n)
        {
            $out .= self::render_news_item(
                $n,
                $show_access,
                true,
                true,
                $pp,
                $link_render,
                $account_render,
                $date_format_service,
                $item_access_service
            );
        }

        $menu_service->set('news');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }

    static public function render_news_item(
        array $n,
        bool $show_access,
        bool $show_heading,
        bool $show_edit_btns,
        PageParamsService $pp,
        LinkRender $link_render,
        AccountRender $account_render,
        DateFormatService $date_format_service,
        ItemAccessService $item_access_service
    ):string
    {
        $out =  '<div class="panel panel-info printview">';
        $out .=  '<div class="panel-body">';

        $out .=  '<div class="media">';
        $out .=  '<div class="media-body">';

        if ($show_heading)
        {
            $out .=  '<h2 class="media-heading">';

            $out .=  $link_render->link_no_attr('news_show', $pp->ary(),
                ['id' => $n['id']], $n['subject']);

            $out .=  '</h2>';
        }

        $out .=  '<p>';
        $out .=  nl2br($n['content']);
        $out .=  '</p>';

        $out .=  '</div>';
        $out .=  '</div>';
        $out .=  '</div>';

        $out .=  '<div class="panel-footer">';

        $out .=  '<dl>';

        if ($n['event_at'])
        {
            $out .=  '<dt>';
            $out .=  'Agendadatum';
            $out .=  '</dt>';
            $out .=  '<dd>';

            $out .=  $date_format_service->get($n['event_at'], 'day', $pp->schema());

            $out .=  '<br><i>';

            if ($n['is_sticky'])
            {
                $out .=  'Dit nieuwsbericht blijft behouden na deze datum.';
            }
            else
            {
                $out .=  'Dit nieuwsbericht wordt automatisch gewist na deze datum.';
            }

            $out .=  '</i>';
            $out .=  '</dd>';
        }
        if ($n['location'])
        {
            $out .=  '<dt>';
            $out .=  'Locatie';
            $out .=  '</dt>';
            $out .=  '<dd>';

            $out .=  htmlspecialchars($n['location'], ENT_QUOTES);

            $out .=  '</dd>';
        }

        if ($show_access)
        {
            $out .=  '<dt>';
            $out .=  'Zichtbaarheid';
            $out .=  '</dt>';
            $out .=  '<dd>';
            $out .=  $item_access_service->get_label($n['access']);
            $out .=  '</dd>';
        }

        $out .=  '</dl>';

        $out .=  '<p><i class="fa fa-user"></i> ';

        $out .=  $account_render->link($n['user_id'], $pp->ary());

        $out .= ' @';
        $out .= $date_format_service->get($n['created_at'], 'day', $pp->schema());

        if ($pp->is_admin() & $show_edit_btns)
        {
            $out .=  '<span class="inline-buttons pull-right hidden-xs">';

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

        return $out;
    }
}
