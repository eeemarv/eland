<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ForumDelPostController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        LinkRender $link_render,
        HeadingRender $heading_render,
        FormTokenService $form_token_service,
        ConfigService $config_service,
        AlertService $alert_service,
        PageParamsService $pp,
        SessionUserService $su,
        ItemAccessService $item_access_service,
        MenuService $menu_service
    ):Response
    {
        if (!$config_service->get('forum_en', $pp->schema()))
        {
            throw new NotFoundHttpException('De forum pagina is niet ingeschakeld in dit systeem.');
        }

        $forum_post = $db->fetchAssoc('select *
            from ' . $pp->schema() . '.forum_posts
            where id = ?', [$id]);

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

        $first_post_id = $db->fetchColumn('select id
            from ' . $pp->schema() . '.forum_posts
            where topic_id = ?
            order by created_at asc
            limit 1', [$forum_topic['id']]);

        if ($first_post_id === $id)
        {
            throw new AccessDeniedHttpException('Verkeerde route om eerste post aan te verwijderen');
        }

        if ($request->isMethod('POST'))
        {
            $errors = [];

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($errors))
            {
                $db->delete($pp->schema() . '.forum_posts', ['id' => $id]);

                $alert_service->success('De forum reactie is verwijderd.');

                $link_render->redirect('forum_topic', $pp->ary(),
                    ['id' => $forum_post['topic_id']]);
            }
        }

        $heading_render->add('Reactie verwijderen?');

        $heading_render->fa('comments-o');

        $out = '<div class="card bg-info">';
        $out .= '<div class="card-body">';

        $out .= '<p>';
        $out .= $forum_post['content'];
        $out .= '</p>';

        $out .= '<form method="post">';

        $out .= $link_render->btn_cancel('forum_topic', $pp->ary(),
            ['id' => $forum_post['topic_id']]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger btn-lg">';
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
