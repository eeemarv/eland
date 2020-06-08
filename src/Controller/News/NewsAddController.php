<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Command\News\NewsAddCommand;
use App\Form\Post\News\NewsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Repository\NewsRepository;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;

class NewsAddController extends AbstractController
{
    public function __invoke(
        Request $request,
        NewsRepository $news_repository,
        MenuService $menu_service,
        AlertService $alert_service,
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        $news_add_command = new NewsAddCommand();

        $form = $this->createForm(NewsType::class,
                $news_add_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $news_add_command = $form->getData();

            $news_item = [
                'subject'   => $news_add_command->subject,
                'location'  => $news_add_command->location,
                'event_at'  => $news_add_command->event_at,
                'content'   => $news_add_command->content,
                'access'    => $news_add_command->access,
                'user_id'   => $su->id(),
            ];

            $id = $news_repository->insert($news_item, $pp->schema());

            $alert_service->success('news_add.success');
            $link_render->redirect('news_show', $pp->ary(),
                ['id' => $id]);
        }

        $menu_service->set('news');

        return $this->render('news/news_add.html.twig', [
            'form'      => $form->createView(),
            'schema'    => $pp->schema(),
        ]);
    }
}
