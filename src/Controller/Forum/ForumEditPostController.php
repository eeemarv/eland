<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\HtmlProcess\HtmlPurifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ForumEditPostController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/forum/{id}/edit-post',
        name: 'forum_edit_post',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'module'        => 'forum',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        AlertService $alert_service,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su,
        HtmlPurifier $html_purifier
    ):Response
    {
        if (!$config_service->get_bool('forum.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Forum module not enabled.');
        }

        $errors = [];

        $content = $request->request->get('content', '');

        $forum_post = $db->fetchAssociative('select *
            from ' . $pp->schema() . '.forum_posts
            where id = ?', [$id], [\PDO::PARAM_INT]);

        if (!isset($forum_post) || !$forum_post)
        {
            throw new NotFoundHttpException('Forum post niet gevonden.');
        }

        $s_post_owner = $su->id() === $forum_post['user_id']
            && $su->is_system_self() && !$pp->is_guest();

        if (!($pp->is_admin() || $s_post_owner))
        {
            throw new AccessDeniedHttpException('Je hebt onvoldoende rechten om deze reactie te verwijderen.');
        }

        $forum_topic = ForumTopicController::get_forum_topic($forum_post['topic_id'], $db, $pp, $item_access_service);

        $first_post_id = $db->fetchOne('select id
            from ' . $pp->schema() . '.forum_posts
            where topic_id = ?
            order by created_at asc
            limit 1', [$forum_topic['id']], [\PDO::PARAM_INT]);

        if ($first_post_id === $id)
        {
            throw new AccessDeniedHttpException('Verkeerde route om eerste post aan te passen');
        }

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

            if (strlen($content) < 2)
            {
                 $errors[] = 'De inhoud van je bericht is te kort.';
            }

            if (!count($errors))
            {
                $post_update = [
                    'content'       => $content,
//                    'edit_count'    => $forum_post['edit_count'] + 1,
                ];

                $db->update($pp->schema() . '.forum_posts',
                    $post_update,
                    ['id' => $forum_post['id']]
                );

                $alert_service->success('Reactie aangepast.');

                return $this->redirectToRoute('forum_topic', array_merge($pp->ary(),
                    ['id' => $forum_topic['id']]));
            }

            $alert_service->error($errors);
        }
        else
        {
            $content = $forum_post['content'];
        }

        $out = '<div class="panel panel-info" id="add">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<textarea name="content" ';
        $out .= 'class="form-control summernote" ';
        $out .= 'id="content" rows="4" required>';
        $out .= $content;
        $out .= '</textarea>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('forum_topic',
            $pp->ary(), ['id' => $forum_topic['id']]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" value="Aanpassen" ';
        $out .= 'class="btn btn-primary btn-lg">';

        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        return $this->render('forum/forum_edit_post.html.twig', [
            'content'       => $out,
            'forum_topic'   => $forum_topic,
            'forum_post'    => $forum_post,
        ]);
    }
}
