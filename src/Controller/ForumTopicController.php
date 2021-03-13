<?php declare(strict_types=1);

namespace App\Controller;

use App\HtmlProcess\HtmlPurifier;
use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
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
use Symfony\Component\Routing\Annotation\Route;

class ForumTopicController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/forum/{id}',
        name: 'forum_topic',
        methods: ['GET', 'POST'],
        priority: 20,
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
        ],
        defaults: [
            'module'        => 'forum',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
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
        MenuService $menu_service,
        HtmlPurifier $html_purifier
    ):Response
    {
        if (!$config_service->get_bool('forum.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Forum module not enabled.');
        }

        $errors = [];

        $content = $request->request->get('content', '');

        $show_access = ($pp->is_user()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        $forum_topic = self::get_forum_topic($id, $db, $pp, $item_access_service);

        $s_topic_owner = $forum_topic['user_id'] === $su->id()
            && $su->is_system_self() && !$pp->is_guest();

        $forum_posts = $db->fetchAllAssociative('select *
            from ' . $pp->schema() . '.forum_posts
            where topic_id = ?
            order by created_at asc', [$id]);

        if ($request->isMethod('POST'))
        {
            if (!($pp->is_user() || $pp->is_admin()))
            {
                throw new AccessDeniedHttpException('Actie niet toegelaten.');
            }

            $content = $html_purifier->purify($content);

            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if (strlen($content) < 2)
            {
                $errors[] = 'De inhoud van je bericht is te kort.';
            }

            if (!count($errors))
            {
                $forum_post = [
                    'content'   => $content,
                    'topic_id'  => $id,
                    'user_id'   => $su->id(),
                ];

                $db->insert($pp->schema() . '.forum_posts', $forum_post);

                $alert_service->success('Reactie toegevoegd.');
                $link_render->redirect('forum_topic', $pp->ary(),
                    ['id' => $id]);
            }

            $alert_service->error($errors);
        }

        $prev = $db->fetchOne('select id
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

        $next = $db->fetchOne('select id
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

        $assets_service->add(['summernote', 'summernote_forum_post.js']);

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

            $out .= '<div class="panel panel-default printview">';

            $out .= '<div class="panel-body">';
            $out .= $post['content'];
            $out .= '</div>';

            $out .= '<div class="panel-footer">';
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

            $out .= '<div class="panel panel-info" id="add">';
            $out .= '<div class="panel-heading">';

            $out .= '<form method="post">';
            $out .= '<div class="form-group">';
            $out .= '<textarea name="content" ';
            $out .= 'class="form-control summernote" ';
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

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }

    public static function get_forum_topic(
        int $id,
        Db $db,
        PageParamsService $pp,
        ItemAccessService $item_access_service
    ):array
    {
        $forum_topic = $db->fetchAssociative('select *
            from ' . $pp->schema() . '.forum_topics
            where id = ?', [$id], [\PDO::PARAM_INT]);

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
