<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Form\Post\DelType;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\ForumRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ForumDelTopicController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/forum/{id}/del-topic',
        name: 'forum_del_topic',
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
        AccountRender $account_render,
        ConfigService $config_service,
        AlertService $alert_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_service->get_bool('forum.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Forum module not enabled.');
        }

        $forum_topic = $forum_repository->get_topic($id, $pp->schema());
        $forum_first_post = $forum_repository->get_first_post($id, $pp->schema());

        if (!($pp->is_admin() || $su->is_owner($forum_topic['user_id'])))
        {
            throw new AccessDeniedHttpException('No rights for this action.');
        }

        $form = $this->createForm(DelType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $forum_repository->del_topic($id, $pp->schema());

            $topic_subject = $forum_topic['subject'];
            $account_str = $account_render->str($forum_topic['user_id'], $pp->schema());

            if ($su->is_owner($forum_topic['user_id']))
            {
                $alert_service->success('Je forum onderwerp "' . $topic_subject . '" is verwijderd.');
            }
            else
            {
                $alert_service->success('Het forum onderwerp "' . $topic_subject . '" van ' . $account_str . ' is verwijderd.');
            }

            return $this->redirectToRoute('forum', $pp->ary());
        }

        return $this->render('forum/forum_del_topic.html.twig', [
            'form'              => $form->createView(),
            'forum_topic'       => $forum_topic,
            'forum_first_post'  => $forum_first_post,
        ]);
    }
}
