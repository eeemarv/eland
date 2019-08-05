<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use cnst\access as cnst_access;

class forum_edit
{
    public function match(Request $request, app $app, string $forum_id):Response
    {
        if (!$app['config']->get('forum_en', $app['tschema']))
        {
            $app['alert']->warning('De forum pagina is niet ingeschakeld.');

            $default_route = $app['config']->get('default_landing_page', $app['tschema']);
            $app['link']->redirect($default_route, $app['pp_ary'], []);
        }

        $row = $app['xdb']->get('forum', $forum_id, $app['tschema']);

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

        if (!$is_topic)
        {
            $topic_id = $forum_post['parent_id'];
        }

        if (!($app['s_admin'] || $s_owner))
        {
            if ($is_topic)
            {
                $app['alert']->error('Je hebt onvoldoende rechten om dit onderwerp aan te passen.');
                $app['link']->redirect('forum_topic', $app['pp_ary'],
                    ['topic_id' => $forum_id]);
            }

            $app['alert']->error('Je hebt onvoldoende rechten om deze reactie aan te passen.');
            $app['link']->redirect('forum_topic', $app['pp_ary'],
                ['topic_id' => $topic_id]);
        }

        if (!$is_topic)
        {
            $row = $app['xdb']->get('forum', $topic_id, $app['tschema']);

            if ($row)
            {
                $topic_post = $row['data'];
            }

            if (!$app['item_access']->is_visible_xdb($topic_post['access']))
            {
                $app['alert']->error('Je hebt geen toegang tot dit forum onderwerp.');
                $app['link']->redirect('forum', $app['pp_ary'], []);
            }
        }

        if ($request->isMethod('POST'))
        {
            $content = $request->request->get('content', '');
            $content = trim(preg_replace('/(<br>)+$/', '', $content));
            $content = str_replace(["\n", "\r", '<p>&nbsp;</p>', '<p><br></p>'], '', $content);
            $content = trim($content);

            $config_htmlpurifier = \HTMLPurifier_Config::createDefault();
            $config_htmlpurifier->set('Cache.DefinitionImpl', null);
            $htmlpurifier = new \HTMLPurifier($config_htmlpurifier);
            $content = $htmlpurifier->purify($content);

            $forum_post = ['content' => $content];

            if ($is_topic)
            {
                $forum_post['subject'] = $request->request->get('subject', '');

                if (!$forum_post['subject'])
                {
                    $errors[] = 'Vul een onderwerp in.';
                }

                $access = $request->request->get('access', '');

                if (!$access)
                {
                    $errors[] = 'Vul een zichtbaarheid in.';
                }
                else
                {
                    $forum_post['access'] = cnst_access::TO_XDB[$access];
                }
            }

            if (strlen($forum_post['content']) < 2)
            {
                $errors[] = 'De inhoud van je bericht is te kort.';
            }

            if ($token_error = $app['form_token']->get_error())
            {
                $errors[] = $token_error;
            }

            if (!count($errors))
            {
                $app['xdb']->set('forum', $forum_id, $forum_post, $app['tschema']);

                if ($is_topic)
                {
                    $app['alert']->success('Onderwerp aangepast.');
                    $app['link']->redirect('forum_topic', $app['pp_ary'],
                        ['topic_id' => $forum_id]);
                }

                $app['alert']->success('Reactie aangepast.');
                $app['link']->redirect('forum_topic', $app['pp_ary'],
                    ['topic_id' => $topic_id]);
            }

            $app['alert']->error($errors);
        }
        else if ($is_topic)
        {
            $access = cnst_access::FROM_XDB[$forum_post['access']];
        }

        $app['assets']->add(['summernote', 'rich_edit.js']);

        if ($is_topic)
        {
            $app['heading']->add('Forum onderwerp aanpassen');
        }
        else
        {
            $app['heading']->add('Reactie aanpassen');
        }

        $app['heading']->fa('comments-o');

        $out = '<div class="panel panel-info" id="add">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        if ($is_topic)
        {
            $out .= '<div class="form-group">';
            $out .= '<input type="text" class="form-control" ';
            $out .= 'id="subject" name="subject" ';
            $out .= 'placeholder="Onderwerp" ';
            $out .= 'value="';
            $out .= $forum_post['subject'];
            $out .= '" required>';
            $out .= '</div>';
        }

        $out .= '<div class="form-group">';
        $out .= '<textarea name="content" ';
        $out .= 'class="form-control rich-edit" ';
        $out .= 'id="content" rows="4" required>';
        $out .= $forum_post['content'];
        $out .= '</textarea>';
        $out .= '</div>';

        if ($is_topic)
        {
            $out .= $app['item_access']->get_radio_buttons('access', $access, 'forum_topic', $app['s_user']);

            $out .= $app['link']->btn_cancel('forum_topic',
                $app['pp_ary'], ['topic_id' => $forum_id]);
        }
        else
        {
            $out .= $app['link']->btn_cancel('forum_topic',
                $app['pp_ary'], ['topic_id' => $topic_id]);
        }

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" value="Aanpassen" ';
        $out .= 'class="btn btn-primary">';

        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('forum');

        return $app['tpl']->get($request);
    }
}
