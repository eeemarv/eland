<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Cnst\AccessCnst;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\XdbService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ForumEditController extends AbstractController
{
    public function forum_edit(
        Request $request,
        string $forum_id,
        XdbService $xdb_service,
        AlertService $alert_service,
        AssetsService $assets_service,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service
    ):Response
    {
        if (!$config_service->get('forum_en', $pp->schema()))
        {
            throw new NotFoundHttpException('De forum pagina is niet ingeschakeld in dit systeem.');
        }

        $row = $xdb_service->get('forum', $forum_id, $pp->schema());

        if ($row)
        {
            $forum_post = $row['data'];
        }

        if (!isset($forum_post))
        {
            $alert_service->error('Post niet gevonden.');
            $link_render->redirect('forum', $pp->ary(), []);
        }

        $s_owner = $forum_post['uid']
            && (int) $forum_post['uid'] === $su->id();

        $is_topic = !isset($forum_post['parent_id']);

        if (!$is_topic)
        {
            $topic_id = $forum_post['parent_id'];
        }

        if (!($pp->is_admin() || $s_owner))
        {
            if ($is_topic)
            {
                $alert_service->error('Je hebt onvoldoende rechten om dit onderwerp aan te passen.');
                $link_render->redirect('forum_topic', $pp->ary(),
                    ['topic_id' => $forum_id]);
            }

            $alert_service->error('Je hebt onvoldoende rechten om deze reactie aan te passen.');
            $link_render->redirect('forum_topic', $pp->ary(),
                ['topic_id' => $topic_id]);
        }

        if (!$is_topic)
        {
            $row = $xdb_service->get('forum', $topic_id, $pp->schema());

            if ($row)
            {
                $topic_post = $row['data'];
            }

            if (!$item_access_service->is_visible_xdb($topic_post['access']))
            {
                $alert_service->error('Je hebt geen toegang tot dit forum onderwerp.');
                $link_render->redirect('forum', $pp->ary(), []);
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
                $xdb_service->set('forum', $forum_id, $forum_post, $pp->schema());

                if ($is_topic)
                {
                    $alert_service->success('Onderwerp aangepast.');
                    $link_render->redirect('forum_topic', $pp->ary(),
                        ['topic_id' => $forum_id]);
                }

                $alert_service->success('Reactie aangepast.');
                $link_render->redirect('forum_topic', $pp->ary(),
                    ['topic_id' => $topic_id]);
            }

            $alert_service->error($errors);
        }
        else if ($is_topic)
        {
            $access = AccessCnst::FROM_XDB[$forum_post['access']];
        }

        $assets_service->add(['summernote', 'summernote_forum_post.js']);

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
            $out .= $item_access_service->get_radio_buttons('access', $access, 'forum_topic', $pp->is_user());

            $out .= $link_render->btn_cancel('forum_topic',
                $pp->ary(), ['topic_id' => $forum_id]);
        }
        else
        {
            $out .= $link_render->btn_cancel('forum_topic',
                $pp->ary(), ['topic_id' => $topic_id]);
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
            'schema'    => $pp->schema(),
        ]);
    }
}
