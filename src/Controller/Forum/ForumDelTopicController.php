<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumDelTopicCommand;
use App\Form\Post\DelType;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\ForumRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
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
        ForumRepository $forum_repository,
        LinkRender $link_render,
        AccountRender $account_render,
        ConfigService $config_service,
        AlertService $alert_service,
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service
    ):Response
    {
        if (!$config_service->get('forum_en', $pp->schema()))
        {
            throw new NotFoundHttpException('The forum module is not enabled in this system.');
        }

        $forum_topic = $forum_repository->get_visible_topic_for_page($id, $pp->schema());

        if (!($su->is_owner($forum_topic['user_id']) || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException('No rights for this action.');
        }

        $first_post = $forum_repository->get_first_post($id, $pp->schema());
        $post_count = $forum_repository->get_post_count($id, $pp->schema());

        $forum_del_topic_command = new ForumDelTopicCommand();

        $form = $this->createForm(DelType::class,
                $forum_del_topic_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            if ($forum_repository->del_topic($id, $pp->schema()))
            {
                $alert_trans_ary = [
                    '%topic_subject%'   => $forum_topic['subject'],
                    '%user%'            => $account_render->str($forum_topic['user_id'], $pp->schema()),
                ];

                $alert_trans_key = 'forum_del_topic.success.';
                $alert_trans_key .= $su->is_owner($forum_topic['user_id']) ? 'personal' : 'admin';

                $alert_service->success($alert_trans_key, $alert_trans_ary);
                $link_render->redirect('forum', $pp->ary(), []);
            }

            $alert_service->error('forum_del_topic.error');
        }

        $menu_service->set('forum');

        return $this->render('forum/forum_del_topic.html.twig', [
            'form'          => $form->createView(),
            'first_post'    => $first_post,
            'post_count'    => $post_count,
            'forum_topic'   => $forum_topic,
            'schema'        => $pp->schema(),
        ]);

/*
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

        $out = '<div class="card fcard fcard-info">';
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

        return $this->render('forum/forum_del_topic.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);

    */
    }
}
