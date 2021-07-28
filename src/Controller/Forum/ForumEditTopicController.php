<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumCommand;
use App\Form\Post\Forum\ForumTopicType;
use App\Render\AccountRender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Repository\ForumRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ForumEditTopicController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/forum/{id}/edit-topic',
        name: 'forum_edit_topic',
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
        AlertService $alert_service,
        AccountRender $account_render,
        ConfigService $config_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_service->get_bool('forum.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Forum module not enabled.');
        }

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

        $forum_command = new ForumCommand();

        $forum_command->subject = $forum_topic['subject'];
        $forum_command->content = $forum_post['content'];
        $forum_command->access = $forum_topic['access'];

        $form = $this->createForm(ForumTopicType::class,
                $forum_command, ['validation_groups' => ['topic']])
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $forum_command = $form->getData();
            $subject = $forum_command->subject;
            $content = $forum_command->content;
            $access = $forum_command->access;

            $forum_repository->update_topic($subject,
                $access, $id, $pp->schema());

            $forum_repository->update_post($content,
                $forum_post['id'], $pp->schema());

            if ($su->is_owner($forum_topic['user_id']))
            {
                $alert_service->success('Je forum onderwerp is aangepast.');
            }
            else
            {
                $alert_service->success('Forum onderwerp van ' .
                    $account_render->get_str($forum_topic['user_id'], $pp->schema()) .
                    ' aangepast.');
            }

            $link_render->redirect('forum_topic', $pp->ary(),
                ['id' => $id]);
        }

        return $this->render('forum/forum_edit_topic.html.twig', [
            'form'          => $form->createView(),
            'forum_topic'   => $forum_topic,
        ]);
    }
}
