<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumCommand;
use App\Command\Forum\ForumPostCommand;
use App\Form\Post\Forum\ForumPostType;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ForumEditPostController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        ForumRepository $forum_repository,
        AlertService $alert_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        AccountRender $account_render,
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service
    ):Response
    {
        $forum_post = $forum_repository->get_post($id, $pp->schema());

        if (!($su->is_owner($forum_post['user_id']) || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException('Access denied.');
        }

        $forum_topic = $forum_repository->get_topic($forum_post['topic_id'], $pp->schema());

        if (!$item_access_service->is_visible($forum_topic['access']))
        {
            throw new AccessDeniedHttpException('Access denied for forum topic.');
        }

        $first_post_id = $forum_repository->get_first_post_id($forum_topic['id'], $pp->schema());

        if ($id === $first_post_id)
        {
            throw new NotFoundHttpException('Wrong route for this action.');
        }

        $forum_command = new ForumCommand();

        $forum_command->content = $forum_post['content'];

        $form = $this->createForm(ForumPostType::class,
                $forum_command, ['validation_groups' => ['post']])
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $forum_command = $form->getData();
            $content = $forum_command->content;

            $forum_repository->update_post($content, $id, $pp->schema());

            if ($su->is_owner($forum_post['user_id']))
            {
                $alert_service->success('forum_edit_post.success.personal', [
                    '%topic_subject%' => $forum_topic['subject'],
                ]);
            }
            else
            {
                $alert_service->success('forum_edit_post.success.admin', [
                    '%topic_subject%'   => $forum_topic['subject'],
                    '%user%'            => $account_render->get_str($forum_post['user_id'], $pp->schema()),
                ]);
            }

            $link_render->redirect('forum_topic', $pp->ary(),
                ['id' => $forum_topic['id']]);
        }

        $menu_service->set('forum');

        return $this->render('forum/forum_edit_post.html.twig', [
            'form'          => $form->createView(),
            'forum_post'    => $forum_post,
            'forum_topic'   => $forum_topic,
            'schema'        => $pp->schema(),
        ]);
    }
}
