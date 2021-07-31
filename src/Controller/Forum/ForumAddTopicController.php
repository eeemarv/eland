<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumCommand;
use App\Form\Post\Forum\ForumTopicType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\ForumRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ForumAddTopicController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/forum/add-topic',
        name: 'forum_add_topic',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'module'        => 'forum',
        ],
    )]

    public function __invoke(
        Request $request,
        ForumRepository $forum_repository,
        ConfigService $config_service,
        AlertService $alert_service,
        SessionUserService $su,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('forum.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Forum module not enabled.');
        }

        $forum_command = new ForumCommand();

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

            $id = $forum_repository->insert_topic($subject, $content,
                $access, $su->id(), $pp->schema());

            $alert_service->success('Forum onderwerp toegevoegd.');

            return $this->redirectToRoute('forum_topic', array_merge($pp->ary(),
                ['id' => $id]));
        }

        return $this->render('forum/forum_add_topic.html.twig', [
            'form'      => $form->createView(),
        ]);
    }
}
