<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ForumController extends AbstractController
{
    public function forum(Request $request, app $app):Response
    {
        if (!$config_service->get('forum_en', $app['pp_schema']))
        {
            $alert_service->warning('De forum pagina is niet ingeschakeld.');
            $link_render->redirect($app['default'], $app['pp_ary'], []);
        }

        $q = $request->query->get('q', '');

        $rows = $xdb_service->get_many([
            'agg_schema' => $app['pp_schema'],
            'agg_type' => 'forum',
            'access' => $item_access_service->get_visible_ary_xdb()],
                'order by event_time desc');

        if (count($rows))
        {
            $forum_posts = [];

            foreach ($rows as $row)
            {
                $replies = $xdb_service->get_many(['agg_schema' => $app['pp_schema'],
                    'agg_type' => 'forum',
                    'data->>\'parent_id\'' => $row['eland_id']]);

                $forum_posts[] = $row['data'] + [
                    'id' 		=> $row['eland_id'],
                    'ts' 		=> $row['event_time'],
                    'replies'	=> count($replies),
                ];
            }
        }

        if ($app['pp_admin'] || $app['pp_user'])
        {
            $btn_top_render->add('forum_add_topic', $app['pp_ary'],
                [], 'Onderwerp toevoegen');
        }

        if ($app['pp_admin'])
        {
            $btn_nav_render->csv();
        }

        $show_visibility = (!$app['pp_guest']
                && $app['intersystem_en'])
            || $app['pp_admin'];

        $heading_render->add('Forum');
        $heading_render->fa('comments-o');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="get">';
        $out .= '<div class="row">';
        $out .= '<div class="col-xs-12">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-search"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="q" name="q" value="' . $q . '" ';
        $out .= 'placeholder="Zoeken">';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $forum_empty = true;

        foreach($forum_posts as $p)
        {
            if ($item_access_service->is_visible_xdb($p['access']))
            {
                $forum_empty = false;
                break;
            }
        }

        if ($forum_empty)
        {
            $out .= '<div class="panel panel-default">';
            $out .= '<div class="panel-heading">';
            $out .= '<p>Er zijn nog geen forum onderwerpen.</p>';
            $out .= '</div></div>';

            $menu_service->set('forum');

            return $this->render('base/navbar.html.twig', [
                'content'   => $out,
                'schema'    => $app['pp_schema'],
            ]);
        }

        $out .= '<div class="panel panel-default printview">';

        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-bordered table-striped table-hover footable csv"';
        $out .= ' data-filter="#q" data-filter-minimum="1">';
        $out .= '<thead>';

        $out .= '<tr>';
        $out .= '<th>Onderwerp</th>';
        $out .= '<th>Reacties</th>';
        $out .= '<th data-hide="phone, tablet">Gebruiker</th>';
        $out .= '<th data-hide="phone, tablet" data-sort-initial="descending" ';
        $out .= 'data-type="numeric">Tijdstip</th>';
        $out .= $show_visibility ? '<th data-hide="phone, tablet">Zichtbaarheid</th>' : '';
        $out .= '</tr>';

        $out .= '</thead>';
        $out .= '<tbody>';

        foreach($forum_posts as $p)
        {
            if (!$item_access_service->is_visible_xdb($p['access']))
            {
                continue;
            }

            $pid = $p['id'];

            $out .= '<tr>';

            $out .= '<td>';
            $out .= $link_render->link_no_attr('forum_topic', $app['pp_ary'],
                ['topic_id' => $pid], $p['subject']);
            $out .= '</td>';

            $out .= '<td>';
            $out .= $p['replies'];
            $out .= '</td>';

            $out .= '<td>';
            $out .= $account_render->link((int) $p['uid'], $app['pp_ary']);
            $out .= '</td>';

            $out .= $date_format_service->get_td($p['ts'], 'min', $app['pp_schema']);

            if ($show_visibility)
            {
                $out .= '<td>';
                $out .= $item_access_service->get_label_xdb($p['access']);
                $out .= '</td>';
            }

            $out .= '</tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';
        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('forum');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
