<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\XdbService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\DBAL\Connection as Db;

class NewsShowController extends AbstractController
{
    public function news_show(
        int $id,
        Db $db,
        AccountRender $account_render,
        AlertService $alert_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        MenuService $menu_service,
        XdbService $xdb_service
    ):Response
    {
        $show_visibility = ($pp->is_user()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        $news_access_ary = $no_access_ary = [];

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
                $no_access_ary[$news_id] = true;
            }
        }

        if (!isset($news[$id]))
        {
            throw new NotFoundHttpException('Dit nieuwsbericht bestaat niet.');
        }

        $news_item = $news[$id];

        if (!$pp->is_admin() && !$news_item['approved'])
        {
            $alert_service->error('Je hebt geen toegang tot dit nieuwsbericht.');
            $link_render->redirect($vr->get('news'), $pp->ary(), []);
        }

        if (isset($no_access_ary[$id]))
        {
            $alert_service->error('Je hebt geen toegang tot dit nieuwsbericht.');
            $link_render->redirect($vr->get('news'), $pp->ary(), []);
        }

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
            $out .= $item_access_service->get_label_xdb($news_item['access']);
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
