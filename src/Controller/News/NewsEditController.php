<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Command\News\NewsCommand;
use App\Form\Post\News\NewsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Repository\NewsRepository;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\PageParamsService;

class NewsEditController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        NewsRepository $news_repository,
        AlertService $alert_service,
        LinkRender $link_render,
        MenuService $menu_service,
        PageParamsService $pp
    ):Response
    {
        $news_item = $news_repository->get($id, $pp->schema());

        $news_command = new NewsCommand();

        $news_command->subject = $news_item['subject'];
        $news_command->event_at = $news_item['event_at'];
        $news_command->location = $news_item['location'];
        $news_command->content = $news_item['content'];
        $news_command->access = $news_item['access'];

        $form = $this->createForm(NewsType::class,
                $news_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $news_command = $form->getData();

            $news_item = [
                'subject'   => $news_command->subject,
                'location'  => $news_command->location,
                'event_at'  => $news_command->event_at,
                'content'   => $news_command->content,
                'access'    => $news_command->access,
            ];

            $news_repository->update($news_item, $id, $pp->schema());

            $alert_service->success('news_edit.success');
            $link_render->redirect('news_show', $pp->ary(),
                ['id' => $id]);
        }

        $menu_service->set('news');

        return $this->render('news/news_edit.html.twig', [
            'form'      => $form->createView(),
            'news_item' => $news_item,
            'schema'    => $pp->schema(),
        ]);
    }
}
