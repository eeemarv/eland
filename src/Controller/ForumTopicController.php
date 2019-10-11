<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\XdbService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ForumTopicController extends AbstractController
{
    public function forum_topic(
        Request $request,
        string $topic_id,
        XdbService $xdb_service,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        MenuService $menu_service
    ):Response
    {
        if (!$config_service->get('forum_en', $app['pp_schema']))
        {
            $alert_service->warning('De forum pagina is niet ingeschakeld.');
            $link_render->redirect($app['r_default'], $app['pp_ary'], []);
        }

        $show_visibility = ($app['pp_user']
                && $config_service->get_intersystem_en($app['pp_schema']))
            || $app['pp_admin'];

        $forum_posts = [];

        $row = $xdb_service->get('forum', $topic_id, $app['pp_schema']);

        if ($row)
        {
            $topic_post = $row['data'];
            $topic_post['ts'] = $row['event_time'];

            if ($row['agg_version'] > 1)
            {
                $topic_post['edit_count'] = $row['agg_version'] - 1;
            }
        }
        else
        {
            $alert_service->error('Dit forum onderwerp bestaat niet');
            $link_render->redirect('forum', $app['pp_ary'], []);
        }

        $topic_post['id'] = $topic_id;

        $s_owner = $topic_post['uid']
            && (int) $topic_post['uid'] === $app['s_id']
            && $app['pp_user'];

        if (!$item_access_service->is_visible_xdb($topic_post['access']) && !$s_owner)
        {
            $alert_service->error('Je hebt geen toegang tot dit forum onderwerp.');
            $link_render->redirect('forum', $app['pp_ary'], []);
        }

        if ($request->isMethod('POST'))
        {
            $errors = [];

            if (!($app['pp_user'] || $app['pp_admin']))
            {
                $alert_service->error('Actie niet toegelaten.');
                $link_render->redirect('forum', $app['pp_ary'], []);
            }

            $content = $request->request->get('content', '');
            $content = trim(preg_replace('/(<br>)+$/', '', $content));
            $content = str_replace(["\n", "\r", '<p>&nbsp;</p>', '<p><br></p>'], '', $content);
            $content = trim($content);

            $config_htmlpurifier = \HTMLPurifier_Config::createDefault();
            $config_htmlpurifier->set('Cache.DefinitionImpl', null);
            $htmlpurifier = new \HTMLPurifier($config_htmlpurifier);
            $content = $htmlpurifier->purify($content);

            $reply = [
                'content'   => $content,
                'parent_id' => $topic_id,
                'uid'       => $app['s_id'],
            ];

            if (strlen($content) < 2)
            {
                $errors[] = 'De inhoud van je bericht is te kort.';
            }

            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if (!count($errors))
            {
                $new_id = substr(sha1(microtime() . $app['pp_schema']), 0, 24);

                $xdb_service->set('forum', $new_id, $reply, $app['pp_schema']);

                $alert_service->success('Reactie toegevoegd.');
                $link_render->redirect('forum_topic', $app['pp_ary'],
                    ['topic_id' => $topic_id]);
            }

            $alert_service->error($errors);
        }

        $forum_posts[] = $topic_post;

        $rows = $xdb_service->get_many(['agg_schema' => $app['pp_schema'],
            'agg_type' => 'forum',
            'data->>\'parent_id\'' => $topic_id], 'order by event_time asc');

        if (count($rows))
        {
            foreach ($rows as $row)
            {
                $data = $row['data'] + ['ts' => $row['event_time'], 'id' => $row['eland_id']];

                if ($row['agg_version'] > 1)
                {
                    $data['edit_count'] = $row['agg_version'] - 1;
                }

                $forum_posts[] = $data;
            }
        }

        $rows = $xdb_service->get_many([
            'agg_schema' => $app['pp_schema'],
            'agg_type' => 'forum',
            'event_time' => ['>' => $topic_post['ts']],
            'access' => $item_access_service->get_visible_ary_xdb(),
        ], 'order by event_time asc limit 1');

        $prev = count($rows) ? reset($rows)['eland_id'] : false;

        $rows = $xdb_service->get_many([
            'agg_schema' => $app['pp_schema'],
            'agg_type' => 'forum',
            'event_time' => ['<' => $topic_post['ts']],
            'access' => $item_access_service->get_visible_ary_xdb(),
        ], 'order by event_time desc limit 1');

        $next = count($rows) ? reset($rows)['eland_id'] : false;

        if ($app['pp_admin'] || $s_owner)
        {
            $btn_top_render->edit('forum_edit', $app['pp_ary'],
                ['forum_id' => $topic_id], 'Onderwerp aanpassen');
            $btn_top_render->del('forum_del', $app['pp_ary'],
                ['forum_id' => $topic_id], 'Onderwerp verwijderen');
        }

        $prev_ary = $prev ? ['topic_id' => $prev] : [];
        $next_ary = $next ? ['topic_id' => $next] : [];

        $btn_nav_render->nav('forum_topic', $app['pp_ary'],
            $prev_ary, $next_ary, false);

        $btn_nav_render->nav_list('forum', $app['pp_ary'],
            [], 'Forum onderwerpen', 'comments');

        $assets_service->add(['summernote', 'summernote_forum_post.js']);

        $heading_render->add($topic_post['subject']);
        $heading_render->fa('comments-o');

        $out = '';

        if ($show_visibility)
        {
            $out .= '<p>Zichtbaarheid: ';
            $out .= $item_access_service->get_label_xdb($topic_post['access']);
            $out .= '</p>';
        }

        foreach ($forum_posts as $p)
        {
            $s_owner = $p['uid']
                && $p['uid'] == $app['s_id']
                && $app['s_system_self']
                && !$app['pp_guest'];

            $pid = $p['id'];

            $out .= '<div class="panel panel-default printview">';

            $out .= '<div class="panel-body">';
            $out .= $p['content'];
            $out .= '</div>';

            $out .= '<div class="panel-footer">';
            $out .= '<p>';
            $out .= $account_render->link((int) $p['uid'], $app['pp_ary']);
            $out .= ' @';
            $out .= $date_format_service->get($p['ts'], 'min', $app['pp_schema']);
            $out .= isset($p['edit_count']) ? ' Aangepast: ' . $p['edit_count'] : '';

            if ($app['pp_admin'] || $s_owner)
            {
                $out .= '<span class="inline-buttons pull-right">';
                $out .= $link_render->link_fa('forum_edit', $app['pp_ary'],
                    ['forum_id' => $pid], 'Aanpassen',
                    ['class' => 'btn btn-primary'], 'pencil');
                $out .= $link_render->link_fa('forum_del', $app['pp_ary'],
                    ['forum_id' => $pid], 'Verwijderen',
                    ['class' => 'btn btn-danger'], 'times');
                $out .= '</span>';
            }

            $out .= '</p>';
            $out .= '</div>';

            $out .= '</div>';
        }

        if ($app['pp_user'] || $app['pp_admin'])
        {
            $out .= '<h3>Reactie toevoegen</h3>';

            $out .= '<div class="panel panel-info" id="add">';
            $out .= '<div class="panel-heading">';

            $out .= '<form method="post">';
            $out .= '<div class="form-group">';
            $out .= '<textarea name="content" ';
            $out .= 'class="form-control summernote" ';
            $out .= 'id="content" rows="4" required>';
            $out .= $content ?? '';
            $out .= '</textarea>';
            $out .= '</div>';

            $out .= '<input type="submit" name="zend" ';
            $out .= 'value="Reactie toevoegen" ';
            $out .= 'class="btn btn-success btn-lg">';
            $out .= $form_token_service->get_hidden_input();

            $out .= '</form>';

            $out .= '</div>';
            $out .= '</div>';
        }

        $menu_service->set('forum');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
