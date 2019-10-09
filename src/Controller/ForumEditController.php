<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Cnst\AccessCnst;

class ForumEditController extends AbstractController
{
    public function forum_edit(Request $request, app $app, string $forum_id):Response
    {
        if (!$config_service->get('forum_en', $app['pp_schema']))
        {
            $alert_service->warning('De forum pagina is niet ingeschakeld.');
            $link_render->redirect($app['r_default'], $app['pp_ary'], []);
        }

        $row = $xdb_service->get('forum', $forum_id, $app['pp_schema']);

        if ($row)
        {
            $forum_post = $row['data'];
        }

        if (!isset($forum_post))
        {
            $alert_service->error('Post niet gevonden.');
            $link_render->redirect('forum', $app['pp_ary'], []);
        }

        $s_owner = $forum_post['uid']
            && (int) $forum_post['uid'] === $app['s_id'];

        $is_topic = !isset($forum_post['parent_id']);

        if (!$is_topic)
        {
            $topic_id = $forum_post['parent_id'];
        }

        if (!($app['pp_admin'] || $s_owner))
        {
            if ($is_topic)
            {
                $alert_service->error('Je hebt onvoldoende rechten om dit onderwerp aan te passen.');
                $link_render->redirect('forum_topic', $app['pp_ary'],
                    ['topic_id' => $forum_id]);
            }

            $alert_service->error('Je hebt onvoldoende rechten om deze reactie aan te passen.');
            $link_render->redirect('forum_topic', $app['pp_ary'],
                ['topic_id' => $topic_id]);
        }

        if (!$is_topic)
        {
            $row = $xdb_service->get('forum', $topic_id, $app['pp_schema']);

            if ($row)
            {
                $topic_post = $row['data'];
            }

            if (!$app['item_access']->is_visible_xdb($topic_post['access']))
            {
                $alert_service->error('Je hebt geen toegang tot dit forum onderwerp.');
                $link_render->redirect('forum', $app['pp_ary'], []);
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
                    $forum_post['access'] = AccessCnst::TO_XDB[$access];
                }
            }

            if (strlen($forum_post['content']) < 2)
            {
                $errors[] = 'De inhoud van je bericht is te kort.';
            }

            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if (!count($errors))
            {
                $xdb_service->set('forum', $forum_id, $forum_post, $app['pp_schema']);

                if ($is_topic)
                {
                    $alert_service->success('Onderwerp aangepast.');
                    $link_render->redirect('forum_topic', $app['pp_ary'],
                        ['topic_id' => $forum_id]);
                }

                $alert_service->success('Reactie aangepast.');
                $link_render->redirect('forum_topic', $app['pp_ary'],
                    ['topic_id' => $topic_id]);
            }

            $alert_service->error($errors);
        }
        else if ($is_topic)
        {
            $access = AccessCnst::FROM_XDB[$forum_post['access']];
        }

        $app['assets']->add(['summernote', 'summernote_forum_post.js']);

        if ($is_topic)
        {
            $heading_render->add('Forum onderwerp aanpassen');
        }
        else
        {
            $heading_render->add('Reactie aanpassen');
        }

        $heading_render->fa('comments-o');

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
        $out .= 'class="form-control summernote" ';
        $out .= 'id="content" rows="4" required>';
        $out .= $forum_post['content'];
        $out .= '</textarea>';
        $out .= '</div>';

        if ($is_topic)
        {
            $out .= $app['item_access']->get_radio_buttons('access', $access, 'forum_topic', $app['pp_user']);

            $out .= $link_render->btn_cancel('forum_topic',
                $app['pp_ary'], ['topic_id' => $forum_id]);
        }
        else
        {
            $out .= $link_render->btn_cancel('forum_topic',
                $app['pp_ary'], ['topic_id' => $topic_id]);
        }

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" value="Aanpassen" ';
        $out .= 'class="btn btn-primary btn-lg">';

        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('forum');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
