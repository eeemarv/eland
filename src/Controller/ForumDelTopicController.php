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

class ForumDelTopicController extends AbstractController
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
        ItemAccessService $item_access_service,
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service
    ):Response
    {
        $errors = [];

        if (!$config_service->get('forum_en', $pp->schema()))
        {
            throw new NotFoundHttpException('De forum pagina is niet ingeschakeld in dit systeem.');
        }

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
                $db->delete($pp->schema() . '.forum_posts', ['topic_id' => $id]);

                $alert_service->success('Het forum onderwerp is verwijderd.');
                $link_render->redirect('forum', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        $forum_post_content = $db->fetchColumn('select content
            from ' . $pp->schema() . '.forum_posts
            where topic_id = ?
            order by created_at asc
            limit 1', [$id]);

        $heading_render->add('Forum onderwerp ');
        $heading_render->add_raw($link_render->link_no_attr('forum_topic', $pp->ary(),
            ['id' => $id], $forum_topic['subject']));
        $heading_render->add(' verwijderen?');

        $heading_render->add_sub_raw('<p class="text-danger">Alle reacties worden verwijderd.</p>');

        $heading_render->fa('comments-o');

        $out = '<div class="card bg-info">';
        $out .= '<div class="card-body">';

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

        $menu_service->set('forum');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
