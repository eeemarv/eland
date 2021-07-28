<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Form\Post\DelType;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\ForumRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ForumDelPostController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/forum/{id}/del-post',
        name: 'forum_del_post',
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
        ForumRepository $forum_repository,
        LinkRender $link_render,
        AccountRender $account_render,
        ConfigService $config_service,
        AlertService $alert_service,
        PageParamsService $pp,
        SessionUserService $su,
        ItemAccessService $item_access_service
    ):Response
    {
        if (!$config_service->get_bool('forum.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Forum module not enabled.');
        }

        $forum_post = $forum_repository->get_post($id, $pp->schema());
        $forum_topic = $forum_repository->get_topic($forum_post['topic_id'], $pp->schema());

        if (!$item_access_service->is_visible($forum_topic['access']))
        {
            throw new AccessDeniedHttpException('Access denied for forum topic ' . $id . '.');
        }

        if (!($su->is_owner($forum_post['user_id']) || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException('No rights for this action.');
        }

        $first_post_id = $forum_repository->get_first_post_id($forum_topic['id'], $pp->schema());

        if ($first_post_id === $id)
        {
            throw new AccessDeniedHttpException('Wrong route for this action.');
        }

        $form = $this->createForm(DelType::class)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            if ($forum_repository->del_post($id, $pp->schema()))
            {
                $topic_subject = $forum_topic['subject'];
                $account_str = $account_render->str($forum_post['user_id'], $pp->schema());

                $alert_msg = $su->is_owner($forum_post['user_id']) ? 'Je post' : 'De post van ' . $account_str;
                $alert_msg .= ' in topic "' . $topic_subject . '" werd gewist.';

                $alert_service->success($alert_msg);
                $link_render->redirect('forum_topic', $pp->ary(), ['id' => $forum_topic['id']]);
            }

            $alert_service->error('Fout bij verwijderen van post');
        }

        return $this->render('forum/forum_del_post.html.twig', [
            'form'          => $form->createView(),
            'forum_topic'   => $forum_topic,
            'forum_post'    => $forum_post,
        ]);
    }
}
