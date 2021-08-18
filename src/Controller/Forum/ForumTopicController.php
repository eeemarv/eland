<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumPostCommand;
use App\Form\Post\Forum\ForumPostType;
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

class ForumTopicController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/forum/{id}',
        name: 'forum_topic',
        methods: ['GET', 'POST'],
        priority: 10,
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
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

        $topic = $forum_repository->get_topic($id, $pp->schema());

        if (!$item_access_service->is_visible($topic['access']))
        {
            throw new AccessDeniedHttpException('Access denied for topic id ' . $id);
        }

        $command = new ForumPostCommand();
        $form = $this->createForm(ForumPostType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $forum_repository->insert_post($command, $su->id(), $id, $pp->schema());

            $alert_service->success('Reactie toegevoegd.');
            return $this->redirectToRoute('forum_topic', array_merge($pp->ary(),
                ['id' => $id]));
        }

        $visible_ary = $item_access_service->get_visible_ary_for_page();
        $posts = $forum_repository->get_topic_posts($id, $pp->schema());
        $prev_id = $forum_repository->get_prev_topic_id($id, $visible_ary, $pp->schema());
        $next_id = $forum_repository->get_next_topic_id($id, $visible_ary, $pp->schema());

        $show_access = ($pp->is_user()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        return $this->render('forum/forum_topic.html.twig', [
            'topic'         => $topic,
            'show_access'   => $show_access,
            'posts'         => $posts,
            'id'            => $id,
            'prev_id'       => $prev_id,
            'next_id'       => $next_id,
            'form'          => $form->createView(),
        ]);
    }
}
