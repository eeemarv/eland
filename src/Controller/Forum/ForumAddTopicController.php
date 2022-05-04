<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumTopicCommand;
use App\Form\Type\Forum\ForumTopicType;
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

        $command = new ForumTopicCommand();

        $form_options = [
            'validation_groups' => ['add'],
        ];

        $form = $this->createForm(ForumTopicType::class, $command, $form_options);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $id = $forum_repository->insert_topic($command, $su->id(), $pp->schema());

            $alert_service->success('Forum onderwerp toegevoegd.');
            return $this->redirectToRoute('forum_topic', [
                ...$pp->ary(),
                'id' => $id,
            ]);
        }

        return $this->render('forum/forum_add_topic.html.twig', [
            'form'      => $form->createView(),
        ]);
    }
}
