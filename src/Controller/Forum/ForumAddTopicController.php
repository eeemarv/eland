<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Command\Forum\ForumCommand;
use App\Form\Post\Forum\ForumTopicType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Repository\ForumRepository;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;

class ForumAddTopicController extends AbstractController
{
    public function __invoke(
        Request $request,
        ForumRepository $forum_repository,
        AlertService $alert_service,
        LinkRender $link_render,
        SessionUserService $su,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
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

            $alert_service->success('forum_add_topic.success');
            $link_render->redirect('forum_topic', $pp->ary(),
                ['id' => $id]);
        }

        $menu_service->set('forum');

        return $this->render('forum/forum_add_topic.html.twig', [
            'form'      => $form->createView(),
            'schema'    => $pp->schema(),
        ]);
    }
}