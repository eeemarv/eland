<?php declare(strict_types=1);

namespace App\Controller\News;

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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class NewsListController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/news',
        name: 'news_list',
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
        Db $db,
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
        if (!$config_service->get_bool('news.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('News module not enabled.');
        }

        $news = self::get_data(
            $db,
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
            $content = self::no_news($menu_service, $pp);

            return $this->render('base/navbar.html.twig', [
                'content'   => $content,
                'schema'    => $pp->schema(),
            ]);
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
        $out .= $show_visibility ? '<th data-hide="phone, tablet">Zichtbaar</th>' : '';
        $out .= '</tr>';
        $out .= '</thead>';

        $out .= '<tbody>';

        foreach ($news as $n)
        {
            $out .= '<tr>';

            $out .= '<td>';

            $out .= $link_render->link_no_attr('news_show', $pp->ary(),
                ['id' => $n['id']], $n['subject']);

            $out .= '</td>';

            $out .= '<td>';

            if ($n['event_at'])
            {
                $out .= $date_format_service->get($n['event_at'], 'day', $pp->schema());
            }
            else
            {
                $out .= '&nbsp;';
            }

            $out .= '</td>';

            if ($show_visibility)
            {
                $out .= '<td>';
                $out .= $item_access_service->get_label($n['access']);
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
        ConfigService $config_service,
        ItemAccessService $item_access_service,
        PageParamsService $pp
    ):array
    {
        $news = [];

        $query = 'select * from ' . $pp->schema() . '.news ';
        $query .= 'where access in (?) ';

        $query .= 'order by event_at ';
        $query .= $config_service->get('news_order_asc', $pp->schema()) === '1' ? 'asc' : 'desc';

        $access_ary = $item_access_service->get_visible_ary_for_page();

        $rows = $db->executeQuery($query, [$access_ary], [Db::PARAM_STR_ARRAY]);

        foreach ($rows as $row)
        {
            $news[$row['id']] = $row;
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
        if($pp->is_admin())
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
        MenuService $menu_service
    ):string
    {
        $out = '<div class="panel panel-default">';
        $out .= '<div class="panel-heading">';
        $out .= '<p>Er zijn momenteel geen nieuwsberichten.</p>';
        $out .= '</div></div>';

        $menu_service->set('news');

        return $out;
    }
}
