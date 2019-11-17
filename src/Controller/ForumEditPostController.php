<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Cnst\AccessCnst;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ForumEditPostController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        AlertService $alert_service,
        AssetsService $assets_service,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
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
            throw new AccessDeniedHttpException('Verkeerde route om eerste post aan te passen');
        }

        if ($request->isMethod('POST'))
        {
            $content = $request->request->get('content', '');
            $content = trim(preg_replace('/(<br>)+$/', '', $content));
            $content = str_replace(["\n", "\r", '<p>&nbsp;</p>', '<p><br></p>'], '', $content);
            $content = trim($content);

            $config_htmlpurifier = \HTMLPurifier_Config::createDefault();
            $config_htmlpurifier->set('Cache.DefinitionImpl', null);
            $htmlpurifier = new \HTMLPurifier($config_htmlpurifier);
            $content = $htmlpurifier->purify($content);

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
                    'last_edit_at'  => gmdate('Y-m-d H:i:s'),
                    'content'       => $content,
                    'edit_count'    => $forum_post['edit_count'] + 1,
                ];

                $db->update($pp->schema() . '.forum_posts',
                    $post_update,
                    ['id' => $forum_post['id']]
                );

                $alert_service->success('Reactie aangepast.');
                $link_render->redirect('forum_topic', $pp->ary(),
                    ['id' => $forum_topic['id']]);
            }

            $alert_service->error($errors);
        }
        else
        {
            $content = $forum_post['content'];
        }

        $assets_service->add(['summernote', 'summernote_forum_post.js']);

        $heading_render->add('Reactie aanpassen');

        $heading_render->fa('comments-o');

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

        $menu_service->set('forum');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
