<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumPostCommand;
use App\Form\Type\Forum\ForumPostType;
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
class ForumEditPostController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/forum/{id}/edit-post',
        name: 'forum_edit_post',
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

        $forum_post = $forum_repository->get_post($id, $pp->schema());
        $forum_topic = $forum_repository->get_topic($forum_post['topic_id'], $pp->schema());

        if (!$item_access_service->is_visible($forum_topic['access']))
        {
            throw new AccessDeniedHttpException('Access denied for forum topic ' . $forum_topic['id']);
        }

        if (!($pp->is_admin() || $su->is_owner($forum_post['user_id'])))
        {
            throw new AccessDeniedHttpException('No rights for this action.');
        }

        $first_post_id = $forum_repository->get_first_post($forum_topic['id'], $pp->schema());

        if ($first_post_id === $id)
        {
            throw new AccessDeniedHttpException('Verkeerde route om eerste post aan te passen');
        }

        $command = new ForumPostCommand();
        $command->content = $forum_post['content'];
        $content = $forum_post['content'];

        $form_options = [
            'validation_groups' => ['edit'],
        ];

        $form = $this->createForm(ForumPostType::class, $command, $form_options);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($command->content === $content)
            {
                $alert_service->Warning('Reactie niet gewijzigd');
            }
            else
            {
                $forum_repository->update_post($id, $command, $pp->schema());

                $alert_service->success('Reactie aangepast');
            }

            return $this->redirectToRoute('forum_topic', [
                ...$pp->ary(),
                'id' => $forum_topic['id'],
            ]);
        }

        return $this->render('forum/forum_edit_post.html.twig', [
            'form'          => $form->createView(),
            'forum_topic'   => $forum_topic,
            'forum_post'    => $forum_post,
        ]);
    }
}
