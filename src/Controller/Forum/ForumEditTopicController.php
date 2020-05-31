<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumEditTopicCommand;
use App\Form\Post\Forum\ForumTopicType;
use App\Render\AccountRender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Repository\ForumRepository;
use App\Service\AlertService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ForumEditTopicController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        ForumRepository $forum_repository,
        AccountRender $account_render,
        AlertService $alert_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service
    ):Response
    {
        $forum_topic = $forum_repository->get_topic($id, $pp->schema());

        if (!$item_access_service->is_visible($forum_topic['access']))
        {
            throw new AccessDeniedHttpException('Access denied for forum topic (1).');
        }

        if (!($su->is_owner($forum_topic['user_id']) || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException('Access Denied for forum topic (2).');
        }

        $forum_post = $forum_repository->get_first_post($id, $pp->schema());

        if (!($su->is_owner($forum_post['user_id']) || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException('Access denied forum forum post.');
        }

        $forum_edit_topic_command = new ForumEditTopicCommand();

        $forum_edit_topic_command->subject = $forum_topic['subject'];
        $forum_edit_topic_command->content = $forum_post['content'];
        $forum_edit_topic_command->access = $forum_topic['access'];

        $form = $this->createForm(ForumTopicType::class,
                $forum_edit_topic_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $forum_edit_topic_command = $form->getData();
            $subject = $forum_edit_topic_command->subject;
            $content = $forum_edit_topic_command->content;
            $access = $forum_edit_topic_command->access;

            $forum_repository->update_topic($subject,
                $access, $id, $pp->schema());

            $forum_repository->update_post($content,
                $forum_post['id'], $pp->schema());

            if ($su->is_owner($forum_topic['user_id']))
            {
                $alert_service->success('forum_edit_topic.success.personal');
            }
            else
            {
                $alert_service->success('forum_edit_topic.success.admin', [
                    '%user%'    => $account_render->get_str($forum_topic['user_id'], $pp->schema()),
                ]);
            }

            $link_render->redirect('forum_topic', $pp->ary(),
                ['id' => $id]);
        }

        $menu_service->set('forum');

        return $this->render('forum/forum_edit_topic.html.twig', [
            'form'          => $form->createView(),
            'forum_topic'   => $forum_topic,
            'schema'        => $pp->schema(),
        ]);
    }
}
