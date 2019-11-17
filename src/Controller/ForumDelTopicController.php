<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service
    ):Response
    {
        if (!$config_service->get('forum_en', $pp->schema()))
        {
            throw new NotFoundHttpException('De forum pagina is niet ingeschakeld in dit systeem.');
        }

        $row = $xdb_service->get('forum', $forum_id, $pp->schema());

        if ($row)
        {
            $forum_post = $row['data'];
        }

        if (!isset($forum_post))
        {
            $alert_service->error('Post niet gevonden.');
            $link_render->redirect('forum', $pp->ary(), []);
        }

        $s_owner = $forum_post['uid']
            && (int) $forum_post['uid'] === $su->id();

        $is_topic = !isset($forum_post['parent_id']);

        if (!($pp->is_admin() || $s_owner))
        {
            if ($is_topic)
            {
                $alert_service->error('Je hebt onvoldoende rechten om dit onderwerp te verwijderen.');
                $link_render->redirect('forum_topic', $pp->ary(),
                    ['topic_id' => $forum_id]);
            }

            $alert_service->error('Je hebt onvoldoende rechten om deze reactie te verwijderen.');
            $link_render->redirect('forum_topic', $pp->ary(),
                ['topic_id' => $forum_post['parent_id']]);
        }

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
            }
            else
            {
                $xdb_service->del('forum', $forum_id, $pp->schema());

                if ($is_topic)
                {
                    $rows = $xdb_service->get_many(['agg_type' => 'forum',
                        'agg_schema' => $pp->schema(),
                        'data->>\'parent_id\'' => $forum_id]);

                    foreach ($rows as $row)
                    {
                        $xdb_service->del('forum', $row['eland_id'], $pp->schema());
                    }

                    $alert_service->success('Het forum onderwerp is verwijderd.');
                    $link_render->redirect('forum', $pp->ary(), []);
                }

                $alert_service->success('De reactie is verwijderd.');

                $link_render->redirect('forum_topic', $pp->ary(),
                    ['topic_id' => $forum_post['parent_id']]);
            }
        }

        if ($is_topic)
        {
            $heading_render->add('Forum onderwerp ');
            $heading_render->add_raw($link_render->link_no_attr('forum_topic', $pp->ary(),
                ['topic_id' => $forum_id], $forum_post['subject']));
            $heading_render->add(' verwijderen?');
        }
        else
        {
            $heading_render->add('Reactie verwijderen?');
        }

        $heading_render->fa('comments-o');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<p>';
        $out .= $forum_post['content'];
        $out .= '</p>';

        $out .= '<form method="post">';

        if ($is_topic)
        {
            $out .= $link_render->btn_cancel('forum_topic', $pp->ary(),
                ['topic_id' => $forum_id]);
        }
        else
        {
            $out .= $link_render->btn_cancel('forum_topic', $pp->ary(),
                ['topic_id' => $forum_post['parent_id']]);
        }

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
