<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use cnst\access as cnst_access;

class forum_add_topic
{
    public function forum_add_topic(Request $request, app $app):Response
    {
        if (!$app['config']->get('forum_en', $app['tschema']))
        {
            $app['alert']->warning('De forum pagina is niet ingeschakeld.');
            $app['link']->redirect($app['r_default'], $app['pp_ary'], []);
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

            $topic = ['content' => $content];

            $topic['subject'] = $request->request->get('subject', '');
            $topic['uid'] = $app['s_id'];

            if (!$topic['subject'])
            {
                 $errors[] = 'Vul een onderwerp in.';
            }

            if (strlen($topic['content']) < 2)
            {
                 $errors[] = 'De inhoud van je bericht is te kort.';
            }

            $access = $request->request->get('access', '');

            if (!$access)
            {
                $errors[] = 'Vul een zichtbaarheid in.';
            }
            else
            {
                $topic['access'] = cnst_access::TO_XDB[$access];
            }

            if ($token_error = $app['form_token']->get_error())
            {
                $errors[] = $token_error;
            }

            if (count($errors))
            {
                $app['alert']->error($errors);
            }
            else
            {
                $topic_id = substr(sha1(random_bytes(16)), 0, 24);

                $app['xdb']->set('forum', $topic_id, $topic, $app['tschema']);

                $app['alert']->success('Onderwerp toegevoegd.');

                $app['link']->redirect('forum_topic', $app['pp_ary'],
                    ['topic_id' => $topic_id]);
            }
        }
        else
        {
            $access = '';
            $topic = [
                'subject'   => '',
                'content'   => '',
            ];
        }

        $app['assets']->add(['summernote', 'rich_edit.js']);

        $app['heading']->add('Nieuw forum onderwerp');
        $app['heading']->fa('comments-o');

        $out = '<div class="panel panel-info" id="add">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="subject" name="subject" ';
        $out .= 'placeholder="Onderwerp" ';
        $out .= 'value="';
        $out .= $topic['subject'];
        $out .= '" required>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<textarea name="content" ';
        $out .= 'class="form-control rich-edit" ';
        $out .= 'id="content" rows="4" required>';
        $out .= $topic['content'];
        $out .= '</textarea>';
        $out .= '</div>';

        $out .= $app['item_access']->get_radio_buttons('access', $access, 'forum_topic', $app['pp_user']);

        $out .= $app['link']->btn_cancel('forum', $app['pp_ary'], []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Onderwerp toevoegen" ';
        $out .= 'class="btn btn-success">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('forum');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['tschema'],
        ]);
    }
}
