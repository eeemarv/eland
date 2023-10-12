<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumTopicCommand;
use App\Form\Type\Forum\ForumTopicType;
use App\Render\AccountRender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\ForumRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
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
            throw new AccessDeniedHttpException('Access denied (1) for forum topic with id ' . $id);
        }

        if (!($su->is_owner($forum_topic['user_id']) || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException('Access Denied (2) for forum topic with id ' . $id);
        }

        $forum_post = $forum_repository->get_first_post($id, $pp->schema());

        if (!($su->is_owner($forum_post['user_id']) || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException('Access denied (3) forum forum post');
        }

        $command = new ForumTopicCommand;

        $command->subject = $forum_topic['subject'];
        $command->content = $forum_post['content'];
        $command->access = $forum_topic['access'];

        $subject = $forum_topic['subject'];
        $content = $forum_post['content'];
        $access = $forum_topic['access'];

        $form_options = [
            'validation_groups' => ['edit'],
        ];

        $form = $this->createForm(ForumTopicType::class, $command, $form_options);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $alert_success_ary = [];

            if ($command->subject !== $subject)
            {
                $alert_success_ary[] = 'Onderwerp aangepast';
            }

            if ($command->content !== $content)
            {
                $alert_success_ary[] = 'Inhoud aangepast';
            }

            if ($command->access !== $access)
            {
                $alert_success_ary[] = 'Zichtbaarheid aangepast';
            }

            if (count($alert_success_ary))
            {
                $forum_repository->update_topic($id, $command, $pp->schema());

                $alert_service->success($alert_success_ary);
            }
            else
            {
                $alert_service->warning('Forum onderwerp niet gewijzigd');
            }

            return $this->redirectToRoute('forum_topic', [
                ...$pp->ary(),
                'id' => $id,
            ]);
        }

        return $this->render('forum/forum_edit_topic.html.twig', [
            'form'          => $form->createView(),
            'forum_topic'   => $forum_topic,
        ]);
    }
}
