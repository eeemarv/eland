<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Form\Post\DelType;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\ForumRepository;
use App\Service\AlertService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ForumDelPostController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        ForumRepository $forum_repository,
        LinkRender $link_render,
        AccountRender $account_render,
        AlertService $alert_service,
        PageParamsService $pp,
        ItemAccessService $item_access_service,
        SessionUserService $su,
        MenuService $menu_service
    ):Response
    {
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
                $alert_trans_ary = [
                    '%topic_subject%'   => $forum_topic['subject'],
                    '%user%'            => $account_render->str($forum_post['user_id'], $pp->schema()),
                ];

                $alert_trans_key = 'forum_del_post.success.';
                $alert_trans_key .= $su->is_owner($forum_post['user_id']) ? 'personal' : 'admin';

                $alert_service->success($alert_trans_key, $alert_trans_ary);
                $link_render->redirect('forum_topic', $pp->ary(), ['id' => $forum_topic['id']]);
            }

            $alert_service->error('forum_del_post.error');
        }

        $menu_service->set('forum');

        return $this->render('forum/forum_del_post.html.twig', [
            'form'          => $form->createView(),
            'forum_post'    => $forum_post,
            'forum_topic'   => $forum_topic,
            'schema'        => $pp->schema(),
        ]);
    }
}