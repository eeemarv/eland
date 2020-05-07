<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\HtmlProcess\HtmlPurifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ForumAddTopicController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        ConfigService $config_service,
        AlertService $alert_service,
        LinkRender $link_render,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        SessionUserService $su,
        PageParamsService $pp,
        MenuService $menu_service,
        HtmlPurifier $html_purifier
    ):Response
    {
        $errors = [];

        if (!$config_service->get('forum_en', $pp->schema()))
        {
            throw new NotFoundHttpException('De forum pagina is niet ingeschakeld in dit systeem.');
        }

        $subject = $request->request->get('subject', '');
        $content = $request->request->get('content', '');
        $access = $request->request->get('access', '');

        if ($request->isMethod('POST'))
        {
            $content = $html_purifier->purify($content);

            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if ($su->is_master())
            {
                $errors[] = 'Het master account kan geen topics aanmaken.';
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
                $forum_topic_insert = [
                    'subject'   => $subject,
                    'access'    => $access,
                    'user_id'   => $su->id(),
                ];

                $db->insert($pp->schema() . '.forum_topics', $forum_topic_insert);

                $id = (int) $db->lastInsertId($pp->schema() . '.forum_topics_id_seq');

                $forum_post_insert = [
                    'content'   => $content,
                    'topic_id'  => $id,
                    'user_id'   => $su->id(),
                ];

                $db->insert($pp->schema() . '.forum_posts', $forum_post_insert);

                $alert_service->success('Onderwerp toegevoegd.');

                $link_render->redirect('forum_topic', $pp->ary(),
                    ['id' => $id]);
            }

            $alert_service->error($errors);
        }

        $heading_render->add('Nieuw forum onderwerp');
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

        $out .= $link_render->btn_cancel('forum', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Onderwerp toevoegen" ';
        $out .= 'class="btn btn-success btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('forum');

        return $this->render('forum/add_topic.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
