<?php declare(strict_types=1);

namespace App\Controller;

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

class NewsListController extends AbstractController
{
    public function __invoke(
        Db $db,
        XdbService $xdb_service,
        ConfigService $config_service,
        ItemAccessService $item_access_service,
        HeadingRender $heading_render,
        BtnTopRender $btn_top_render,
        BtnNavRender $btn_nav_render,
        DateFormatService $date_format_service,
        LinkRender $link_render,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        $news = self::get_data(
            $db,
            $xdb_service,
            $config_service,
            $item_access_service,
            $pp
        );

        self::set_heading_and_btns(
            true,
            $heading_render,
            $btn_top_render,
            $btn_nav_render,
            $pp
        );

        $show_visibility = ($pp->is_user()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        if ($pp->is_admin())
        {
            $btn_nav_render->csv();
        }

        if (!count($news))
        {
            return self::no_news($menu_service, $pp);
        }

        $out = '<div class="panel panel-warning printview">';
        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-striped ';
        $out .= 'table-hover table-bordered footable csv">';

        $out .= '<thead>';
        $out .= '<tr>';
        $out .= '<th>Titel</th>';
        $out .= '<th data-hide="phone" ';
        $out .= 'data-sort-initial="descending">Agendadatum</th>';
        $out .= $pp->is_admin() ? '<th data-hide="phone">Goedgekeurd</th>' : '';
        $out .= $show_visibility ? '<th data-hide="phone, tablet">Zichtbaar</th>' : '';
        $out .= '</tr>';
        $out .= '</thead>';

        $out .= '<tbody>';

        foreach ($news as $n)
        {
            $out .= '<tr';
            $out .= $n['approved'] ? '' : ' class="inactive"';
            $out .= '>';

            $out .= '<td>';

            $out .= $link_render->link_no_attr('news_show', $pp->ary(),
                ['id' => $n['id']], $n['headline']);

            $out .= '</td>';

            $out .= $date_format_service->get_td($n['itemdate'], 'day', $pp->schema());

            if ($pp->is_admin())
            {
                $out .= '<td>';
                $out .= $n['approved'] ? 'Ja' : 'Nee';
                $out .= '</td>';
            }

            if ($show_visibility)
            {
                $out .= '<td>';
                $out .= $item_access_service->get_label_xdb($n['access']);
                $out .= '</td>';
            }

            $out .= '</tr>';
        }

        $out .= '</tbody>';
        $out .= '</table></div></div>';

        $menu_service->set('news');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }

    public static function get_data(
        Db $db,
        XdbService $xdb_service,
        ConfigService $config_service,
        ItemAccessService $item_access_service,
        PageParamsService $pp
    ):array
    {
        $news = $news_access_ary = [];

        $rows = $xdb_service->get_many([
            'agg_schema' => $pp->schema(),
            'agg_type' => 'news_access',
        ]);

        foreach ($rows as $row)
        {
            $access = $row['data']['access'];
            $news_access_ary[$row['eland_id']] = $access;
        }

        $query = 'select * from ' . $pp->schema() . '.news';

        if(!$pp->is_admin())
        {
            $query .= ' where approved = \'t\'';
        }

        $query .= ' order by itemdate ';
        $query .= $config_service->get('news_order_asc', $pp->schema()) === '1' ? 'asc' : 'desc';

        $st = $db->prepare($query);
        $st->execute();

        while ($row = $st->fetch())
        {
            $news_id = $row['id'];
            $news[$news_id] = $row;

            if (!isset($news_access_ary[$news_id]))
            {
                $xdb_service->set('news_access', (string) $news_id, [
                    'access' => 'interlets',
                ], $pp->schema());

                $news[$news_id]['access'] = 'interlets';
            }
            else
            {
                $news[$news_id]['access'] = $news_access_ary[$news_id];
            }

            if (!$item_access_service->is_visible_xdb($news[$news_id]['access']))
            {
                unset($news[$news_id]);
            }
        }

        return $news;
    }

    public static function set_heading_and_btns(
        bool $is_list,
        HeadingRender $heading_render,
        BtnTopRender $btn_top_render,
        BtnNavRender $btn_nav_render,
        PageParamsService $pp
    ):void
    {
        if($pp->is_user() || $pp->is_admin())
        {
            $btn_top_render->add('news_add', $pp->ary(),
                [], 'Nieuws toevoegen');
        }

        $heading_render->add('Nieuws');
        $heading_render->fa('calendar-o');

        $btn_nav_render->view('news_list', $pp->ary(),
            [], 'Lijst', 'align-justify', $is_list);

        $btn_nav_render->view('news_extended', $pp->ary(),
            [], 'Lijst met omschrijvingen', 'th-list', !$is_list);
    }

    public static function no_news(
        MenuService $menu_service,
        PageParamsService $pp
    ):Response
    {
        $out = '<div class="panel panel-default">';
        $out .= '<div class="panel-heading">';
        $out .= '<p>Er zijn momenteel geen nieuwsberichten.</p>';
        $out .= '</div></div>';

        $menu_service->set('news');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}