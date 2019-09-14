<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class forum_del
{
    public function forum_del(Request $request, app $app, string $forum_id):Response
    {
        if (!$app['config']->get('forum_en', $app['pp_schema']))
        {
            $app['alert']->warning('De forum pagina is niet ingeschakeld.');
            $app['link']->redirect($app['r_default'], $app['pp_ary'], []);
        }

        $row = $app['xdb']->get('forum', $forum_id, $app['pp_schema']);

        if ($row)
        {
            $forum_post = $row['data'];
        }

        if (!isset($forum_post))
        {
            $app['alert']->error('Post niet gevonden.');
            $app['link']->redirect('forum', $app['pp_ary'], []);
        }

        $s_owner = $forum_post['uid']
            && (int) $forum_post['uid'] === $app['s_id'];

        $is_topic = !isset($forum_post['parent_id']);

        if (!($app['pp_admin'] || $s_owner))
        {
            if ($is_topic)
            {
                $app['alert']->error('Je hebt onvoldoende rechten om dit onderwerp te verwijderen.');
                $app['link']->redirect('forum_topic', $app['pp_ary'],
                    ['topic_id' => $forum_id]);
            }

            $app['alert']->error('Je hebt onvoldoende rechten om deze reactie te verwijderen.');
            $app['link']->redirect('forum_topic', $app['pp_ary'],
                ['topic_id' => $forum_post['parent_id']]);
        }

        if ($request->isMethod('POST'))
        {
            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);
            }
            else
            {
                $app['xdb']->del('forum', $forum_id, $app['pp_schema']);

                if ($is_topic)
                {
                    $rows = $app['xdb']->get_many(['agg_type' => 'forum',
                        'agg_schema' => $app['pp_schema'],
                        'data->>\'parent_id\'' => $forum_id]);

                    foreach ($rows as $row)
                    {
                        $app['xdb']->del('forum', $row['eland_id'], $app['pp_schema']);
                    }

                    $app['alert']->success('Het forum onderwerp is verwijderd.');
                    $app['link']->redirect('forum', $app['pp_ary'], []);
                }

                $app['alert']->success('De reactie is verwijderd.');

                $app['link']->redirect('forum_topic', $app['pp_ary'],
                    ['topic_id' => $forum_post['parent_id']]);
            }
        }

        if ($is_topic)
        {
            $app['heading']->add('Forum onderwerp ');
            $app['heading']->add_raw($app['link']->link_no_attr('forum_topic', $app['pp_ary'],
                ['topic_id' => $forum_id], $forum_post['subject']));
            $app['heading']->add(' verwijderen?');
        }
        else
        {
            $app['heading']->add('Reactie verwijderen?');
        }

        $app['heading']->fa('comments-o');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<p>';
        $out .= $forum_post['content'];
        $out .= '</p>';

        $out .= '<form method="post">';

        if ($is_topic)
        {
            $out .= $app['link']->btn_cancel('forum_topic', $app['pp_ary'],
                ['topic_id' => $forum_id]);
        }
        else
        {
            $out .= $app['link']->btn_cancel('forum_topic', $app['pp_ary'],
                ['topic_id' => $forum_post['parent_id']]);
        }

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('forum');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
