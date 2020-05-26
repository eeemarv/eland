<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumAddPostCommand;
use App\Form\Post\Forum\ForumAddPostType;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\LinkRender;
use App\Repository\ForumRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
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

class ForumTopicController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        ForumRepository $forum_repository,
        AlertService $alert_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service
    ):Response
    {
        $content = $request->request->get('content', '');

        $show_access = ($pp->is_user()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        $forum_topic = $forum_repository->get_topic($id, $pp->schema());

        if (!$item_access_service->is_visible($forum_topic['access']))
        {
            throw new AccessDeniedHttpException('Access denied for forum topic.');
        }

        $forum_posts = $forum_repository->get_topic_posts($id, $pp->schema());

        $s_topic_owner = $forum_topic['user_id'] === $su->id()
            && $su->is_system_self() && !$pp->is_guest();

        $forum_add_post_command = new ForumAddPostCommand();

        $form = $this->createForm(ForumAddPostType::class,
                $forum_add_post_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid()
            && ($pp->is_user() || $pp->is_admin()))
        {
            $forum_add_post_command = $form->getData();
            $content = $forum_add_post_command->content;

            $forum_repository->insert_post($content,
                $su->id(), $id, $pp->schema());

            $alert_service->success('forum_topic.add_post.success',
                ['%topic_subject%' => $forum_topic['subject']]);
            $link_render->redirect('forum_topic', $pp->ary(),
                ['id' => $id]);
        }

        $stmt_prev = $db->executeQuery('select id
            from ' . $pp->schema() . '.forum_topics
            where last_edit_at > ?
                and access in (?)
            order by last_edit_at asc
            limit 1', [
                $forum_topic['last_edit_at'],
                $item_access_service->get_visible_ary_for_page()
            ], [
                \PDO::PARAM_STR,
                Db::PARAM_STR_ARRAY,
            ]);

        $prev = $stmt_prev->fetchColumn();

        $stmt_next = $db->executeQuery('select id
            from ' . $pp->schema() . '.forum_topics
            where last_edit_at < ?
                and access in (?)
            order by last_edit_at desc
            limit 1', [
                $forum_topic['last_edit_at'],
                $item_access_service->get_visible_ary_for_page()
            ], [
                \PDO::PARAM_STR,
                Db::PARAM_STR_ARRAY,
            ]);

        $next = $stmt_next->fetchColumn();

        if ($pp->is_admin() || $s_topic_owner)
        {
            $btn_top_render->edit('forum_edit_topic', $pp->ary(),
                ['id' => $id], 'Onderwerp aanpassen');

            $btn_top_render->del('forum_del_topic', $pp->ary(),
                ['id' => $id], 'Onderwerp verwijderen');
        }

        $prev_ary = $prev ? ['id' => $prev] : [];
        $next_ary = $next ? ['id' => $next] : [];

        $btn_nav_render->nav('forum_topic', $pp->ary(),
            $prev_ary, $next_ary, false);

        $btn_nav_render->nav_list('forum', $pp->ary(),
            [], 'Forum onderwerpen', 'comments');

        $menu_service->set('forum');

        return $this->render('forum/forum_topic.html.twig', [
            'forum_topic'   => $forum_topic,
            'forum_posts'   => $forum_posts,
            'show_access'   => $show_access,
            'form'          => $form->createView(),
            'schema'        => $pp->schema(),
        ]);
    }
}
