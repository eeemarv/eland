<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumDelTopicCommand;
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

class ForumDelTopicController extends AbstractController
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
        $forum_topic = $forum_repository->get_topic($id, $pp->schema());

        if (!$item_access_service->is_visible($forum_topic['access']))
        {
            throw new AccessDeniedHttpException('Access denied for forum topic.');
        }

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
    }
}
