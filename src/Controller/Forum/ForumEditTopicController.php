<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\HtmlProcess\HtmlPurifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ForumEditTopicController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service,
        HtmlPurifier $html_purifier
    ):Response
    {
        $errors = [];

        $subject = $request->request->get('subject', '');
        $content = $request->request->get('content', '');
        $access = $request->request->get('access', '');

        $forum_topic = ForumTopicController::get_forum_topic($id, $db, $pp, $item_access_service);

        $s_topic_owner = $forum_topic['user_id'] === $su->id()
            && $su->is_system_self() && !$pp->is_guest();

        if (!($s_topic_owner || $su->is_admin()))
        {
            throw new AccessDeniedHttpException('Je hebt onvoldoende rechten om dit topic aan te passen.');
        }

        $forum_post = $db->fetchAssoc('select *
            from ' . $pp->schema() . '.forum_posts
            where topic_id = ?
            order by created_at asc
            limit 1', [$id]);

        if ($request->isMethod('POST'))
        {
            $content = $html_purifier->purify($content);

            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if ($su->is_master())
            {
                $errors[] = 'Het master account kan geen topics aanpassen.';
            }

            if (!$subject)
            {
                 $errors[] = 'Vul een onderwerp in.';
            }

            if (strlen($content) < 2)
            {
                 $errors[] = 'De inhoud van je bericht is te kort.';
            }

            if (!$access)
            {
                $errors[] = 'Vul een zichtbaarheid in.';
            }

            if (!count($errors))
            {
                $topic_update = [
                    'subject'       => $subject,
                    'access'        => $access,
                ];

                $db->update($pp->schema() . '.forum_topics',
                    $topic_update,
                    ['id' => $id]
                );

                $post_update = [
                    'content'       => $content,
//                    'edit_count'    => $forum_post['edit_count'] + 1,
                ];

                $db->update($pp->schema() . '.forum_posts',
                    $post_update,
                    ['id' => $forum_post['id']]
                );

                $alert_service->success('Onderwerp aangepast.');
                $link_render->redirect('forum_topic', $pp->ary(),
                    ['id' => $id]);
            }

            $alert_service->error($errors);
        }
        else
        {
            $subject = $forum_topic['subject'];
            $content = $forum_post['content'];
            $access = $forum_topic['access'];
        }

        $heading_render->add('Forum onderwerp aanpassen');

        $heading_render->fa('comments-o');

        $out = '<div class="card fcard fcard-info">';
        $out .= '<div class="card-body">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="subject" name="subject" ';
        $out .= 'placeholder="Onderwerp" ';
        $out .= 'value="';
        $out .= $subject;
        $out .= '" required>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<textarea name="content" ';
        $out .= 'class="form-control" data-summernote ';
        $out .= 'id="content" rows="4" required>';
        $out .= $content;
        $out .= '</textarea>';
        $out .= '</div>';

        $out .= $item_access_service->get_radio_buttons('access', $access, 'forum_topic', $pp->is_user());

        $out .= $link_render->btn_cancel('forum_topic',
            $pp->ary(), ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" value="Aanpassen" ';
        $out .= 'class="btn btn-primary btn-lg">';

        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('forum');

        return $this->render('forum/forum_edit_topic.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
