<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class forum_topic
{
    public function forum_topic(Request $request, app $app, string $topic_id):Response
    {
        if (!$app['config']->get('forum_en', $app['pp_schema']))
        {
            $app['alert']->warning('De forum pagina is niet ingeschakeld.');
            $app['link']->redirect($app['r_default'], $app['pp_ary'], []);
        }

        $show_visibility = ($app['pp_user']
                && $app['intersystem_en'])
            || $app['pp_admin'];

        $forum_posts = [];

        $row = $app['xdb']->get('forum', $topic_id, $app['pp_schema']);

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
            $app['alert']->error('Dit forum onderwerp bestaat niet');
            $app['link']->redirect('forum', $app['pp_ary'], []);
        }

        $topic_post['id'] = $topic_id;

        $s_owner = $topic_post['uid']
            && (int) $topic_post['uid'] === $app['s_id']
            && $app['pp_user'];

        if (!$app['item_access']->is_visible_xdb($topic_post['access']) && !$s_owner)
        {
            $app['alert']->error('Je hebt geen toegang tot dit forum onderwerp.');
            $app['link']->redirect('forum', $app['pp_ary'], []);
        }

        if ($request->isMethod('POST'))
        {
            $errors = [];

            if (!($app['pp_user'] || $app['pp_admin']))
            {
                $app['alert']->error('Actie niet toegelaten.');
                $app['link']->redirect('forum', $app['pp_ary'], []);
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

            if ($token_error = $app['form_token']->get_error())
            {
                $errors[] = $token_error;
            }

            if (!count($errors))
            {
                $new_id = substr(sha1(microtime() . $app['pp_schema']), 0, 24);

                $app['xdb']->set('forum', $new_id, $reply, $app['pp_schema']);

                $app['alert']->success('Reactie toegevoegd.');
                $app['link']->redirect('forum_topic', $app['pp_ary'],
                    ['topic_id' => $topic_id]);
            }

            $app['alert']->error($errors);
        }

        $forum_posts[] = $topic_post;

        $rows = $app['xdb']->get_many(['agg_schema' => $app['pp_schema'],
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

        $rows = $app['xdb']->get_many([
            'agg_schema' => $app['pp_schema'],
            'agg_type' => 'forum',
            'event_time' => ['>' => $topic_post['ts']],
            'access' => $app['item_access']->get_visible_ary_xdb(),
        ], 'order by event_time asc limit 1');

        $prev = count($rows) ? reset($rows)['eland_id'] : false;

        $rows = $app['xdb']->get_many([
            'agg_schema' => $app['pp_schema'],
            'agg_type' => 'forum',
            'event_time' => ['<' => $topic_post['ts']],
            'access' => $app['item_access']->get_visible_ary_xdb(),
        ], 'order by event_time desc limit 1');

        $next = count($rows) ? reset($rows)['eland_id'] : false;

        if ($app['pp_admin'] || $s_owner)
        {
            $app['btn_top']->edit('forum_edit', $app['pp_ary'],
                ['forum_id' => $topic_id], 'Onderwerp aanpassen');
            $app['btn_top']->del('forum_del', $app['pp_ary'],
                ['forum_id' => $topic_id], 'Onderwerp verwijderen');
        }

        $prev_ary = $prev ? ['topic_id' => $prev] : [];
        $next_ary = $next ? ['topic_id' => $next] : [];

        $app['btn_nav']->nav('forum_topic', $app['pp_ary'],
            $prev_ary, $next_ary, false);

        $app['btn_nav']->nav_list('forum', $app['pp_ary'],
            [], 'Forum onderwerpen', 'comments');

        $app['assets']->add(['summernote', 'rich_edit.js']);

        $app['heading']->add($topic_post['subject']);
        $app['heading']->fa('comments-o');

        $out = '';

        if ($show_visibility)
        {
            $out .= '<p>Zichtbaarheid: ';
            $out .= $app['item_access']->get_label_xdb($topic_post['access']);
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
            $out .= $app['account']->link((int) $p['uid'], $app['pp_ary']);
            $out .= ' @';
            $out .= $app['date_format']->get($p['ts'], 'min', $app['pp_schema']);
            $out .= isset($p['edit_count']) ? ' Aangepast: ' . $p['edit_count'] : '';

            if ($app['pp_admin'] || $s_owner)
            {
                $out .= '<span class="inline-buttons pull-right">';
                $out .= $app['link']->link_fa('forum_edit', $app['pp_ary'],
                    ['forum_id' => $pid], 'Aanpassen',
                    ['class' => 'btn btn-primary'], 'pencil');
                $out .= $app['link']->link_fa('forum_del', $app['pp_ary'],
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
            $out .= 'class="form-control rich-edit" ';
            $out .= 'id="content" rows="4" required>';
            $out .= $content ?? '';
            $out .= '</textarea>';
            $out .= '</div>';

            $out .= '<input type="submit" name="zend" ';
            $out .= 'value="Reactie toevoegen" ';
            $out .= 'class="btn btn-success btn-lg">';
            $out .= $app['form_token']->get_hidden_input();

            $out .= '</form>';

            $out .= '</div>';
            $out .= '</div>';
        }

        $app['menu']->set('forum');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
