<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumAddPostCommand;
use App\Form\Post\Forum\ForumAddPostType;
use App\HtmlProcess\HtmlPurifier;
use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Repository\ForumRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
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

class ForumTopicController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        ForumRepository $forum_repository,
        AccountRender $account_render,
        AlertService $alert_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
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

        $forum_topic = self::get_forum_topic($id, $db, $pp, $item_access_service);

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

        $heading_render->add($forum_topic['subject']);
        $heading_render->fa('comments-o');

        $out = '';

        if ($show_access)
        {
            $out .= '<p>Toegang: ';
            $out .= $item_access_service->get_label($forum_topic['access']);
            $out .= '</p>';
        }

        $first_post = true;

        foreach ($forum_posts as $post)
        {
            $s_post_owner = $post['user_id'] === $su->id()
                && $su->is_system_self()
                && !$pp->is_guest();

            $post_id = $post['id'];

            $out .= '<div class="card card-default printview mb-3">';

            $out .= '<div class="card-body">';
            $out .= $post['content'];
            $out .= '</div>';

            $out .= '<div class="card-footer">';
            $out .= '<p>';
            $out .= $account_render->link((int) $post['user_id'], $pp->ary());
            $out .= ' @';
            $out .= $date_format_service->get($post['created_at'], 'min', $pp->schema());
            $out .= $post['edit_count'] ? ' Aangepast: ' . $post['edit_count'] : '';

            if ($pp->is_admin() || $s_post_owner)
            {
                $out .= '<span class="inline-buttons pull-right">';

                if ($first_post)
                {
                    $out .= $link_render->link_fa('forum_edit_topic', $pp->ary(),
                        ['id' => $id], 'Aanpassen',
                        ['class' => 'btn btn-primary'], 'pencil');

                    $out .= $link_render->link_fa('forum_del_topic', $pp->ary(),
                        ['id' => $id], 'Verwijderen',
                        ['class' => 'btn btn-danger'], 'times');
                }
                else
                {
                    $out .= $link_render->link_fa('forum_edit_post', $pp->ary(),
                        ['id' => $post_id], 'Aanpassen',
                        ['class' => 'btn btn-primary'], 'pencil');

                    $out .= $link_render->link_fa('forum_del_post', $pp->ary(),
                        ['id' => $post_id], 'Verwijderen',
                        ['class' => 'btn btn-danger'], 'times');
                }

                $out .= '</span>';
            }

            $out .= '</p>';
            $out .= '</div>';

            $out .= '</div>';

            $first_post = false;
        }

        if ($pp->is_user() || $pp->is_admin())
        {
            $out .= '<h3>Reactie toevoegen</h3>';

            $out .= '<div class="card fcard fcard-info">';
            $out .= '<div class="card-body">';

            $out .= '<form method="post">';
            $out .= '<div class="form-group">';
            $out .= '<textarea name="content" ';
            $out .= 'class="form-control" data-summernote ';
            $out .= 'id="content" rows="4" required>';
            $out .= $content;
            $out .= '</textarea>';
            $out .= '</div>';

            $out .= '<input type="submit" name="zend" ';
            $out .= 'value="Reactie toevoegen" ';
            $out .= 'class="btn btn-success btn-lg">';
            $out .= $form_token_service->get_hidden_input();

            $out .= '</form>';

            $out .= '</div>';
            $out .= '</div>';
        }

        $menu_service->set('forum');

        return $this->render('forum/forum_topic.html.twig', [
            'forum_topic'   => $forum_topic,
            'forum_posts'   => $forum_posts,
            'form'          => $form,
            'content'       => $out,
            'schema'        => $pp->schema(),
        ]);
    }

    public static function get_forum_topic(
        int $id,
        Db $db,
        PageParamsService $pp,
        ItemAccessService $item_access_service
    ):array
    {
        $forum_topic = $db->fetchAssoc('select *
            from ' . $pp->schema() . '.forum_topics
            where id = ?', [$id]);

        if (!isset($forum_topic) || !$forum_topic)
        {
            throw new NotFoundHttpException('Forum onderwerp niet gevonden.');
        }

        if (!$item_access_service->is_visible($forum_topic['access']))
        {
            throw new AccessDeniedHttpException('Je hebt geen toegang tot dit forum onderwerp.');
        }

        return $forum_topic;
    }
}
