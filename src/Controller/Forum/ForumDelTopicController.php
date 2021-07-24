<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ForumDelTopicController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/forum/{id}/del-topic',
        name: 'forum_del_topic',
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
        LinkRender $link_render,
        FormTokenService $form_token_service,
        ConfigService $config_service,
        AlertService $alert_service,
        ItemAccessService $item_access_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_service->get_bool('forum.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Forum module not enabled.');
        }

        $errors = [];

        $forum_topic = ForumTopicController::get_forum_topic($id, $db, $pp, $item_access_service);

        $s_topic_owner = $forum_topic['user_id'] === $su->id()
            && $su->is_system_self() && !$pp->is_guest();

        if (!($s_topic_owner || $su->is_admin()))
        {
            throw new AccessDeniedHttpException('Je hebt onvoldoende rechten om dit topic te verwijderen.');
        }

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($errors))
            {
                $db->delete($pp->schema() . '.forum_topics', ['id' => $id]);

                $alert_service->success('Het forum onderwerp is verwijderd.');
                $link_render->redirect('forum', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        $forum_post_content = $db->fetchOne('select content
            from ' . $pp->schema() . '.forum_posts
            where topic_id = ?
            order by created_at asc
            limit 1',
            [$id], [\PDO::PARAM_INT]);

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<p>';
        $out .= $forum_post_content;
        $out .= '</p>';

        $out .= '<form method="post">';

        $out .= $link_render->btn_cancel('forum_topic', $pp->ary(),
            ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        return $this->render('forum/forum_del_topic.html.twig', [
            'content'       => $out,
            'forum_topic'   => $forum_topic,
        ]);
    }
}
