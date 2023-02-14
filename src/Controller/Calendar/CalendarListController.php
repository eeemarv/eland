<?php declare(strict_types=1);

namespace App\Controller\Calendar;

use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsController]
class CalendarListController extends AbstractController
{
    public function __invoke(
        Db $db,
        ConfigService $config_service,
        ItemAccessService $item_access_service,
        DateFormatService $date_format_service,
        LinkRender $link_render,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('calendar.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Calendar module not enabled.');
        }

        $news = self::get_data(
            $db,
            $config_service,
            $item_access_service,
            $pp
        );

        $show_visibility = ($pp->is_user()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

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

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
        ]);
    }

    public static function get_data(
        Db $db,
        ConfigService $config_service,
        ItemAccessService $item_access_service,
        PageParamsService $pp
    ):array
    {
        $calendar_items = [];

        $query = 'select ci.subject, cip.start_at
            from ' . $pp->schema() . '.calendar_items ci, ' ;
        $query .= 'where ci.access in (?) ';

        $query .= 'order by event_at ';
        $query .= $config_service->get_bool('news.sort.asc', $pp->schema()) ? 'asc' : 'desc';

        $access_ary = $item_access_service->get_visible_ary_for_page();

        $res = $db->executeQuery($query, [$access_ary], [Db::PARAM_STR_ARRAY]);

        while ($row = $res->fetchAssociative())
        {
            $news[$row['id']] = $row;
        }

        return $news;
    }
}
