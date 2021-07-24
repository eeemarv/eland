<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\HtmlProcess\HtmlPurifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ForumAddTopicController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/forum/add-topic',
        name: 'forum_add_topic',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'module'        => 'forum',
        ],
    )]

    public function __invoke(
        Request $request,
        Db $db,
        ConfigService $config_service,
        AlertService $alert_service,
        LinkRender $link_render,
        FormTokenService $form_token_service,
        ItemAccessService $item_access_service,
        SessionUserService $su,
        PageParamsService $pp,
        HtmlPurifier $html_purifier
    ):Response
    {
        if (!$config_service->get_bool('forum.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Forum module not enabled.');
        }

        $errors = [];

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

        $out = '<div class="panel panel-info" id="add">';
        $out .= '<div class="panel-heading">';

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
        $out .= 'class="form-control summernote" ';
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

        return $this->render('forum/forum_add_topic.html.twig', [
            'content'   => $out,
        ]);
    }
}
