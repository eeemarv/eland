<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Command\News\NewsDelCommand;
use App\Form\Post\DelType;
use App\Render\LinkRender;
use App\Repository\NewsRepository;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NewsDelController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        NewsRepository $news_repository,
        AlertService $alert_service,
        LinkRender $link_render,
        MenuService $menu_service,
        PageParamsService $pp,
        VarRouteService $vr
    ):Response
    {
        $news = $news_repository->get_visible_for_page($id, $pp->schema());

        $news_del_command = new NewsDelCommand();

        $form = $this->createForm(DelType::class,
                $news_del_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            if ($news_repository->del($id, $pp->schema()))
            {
                $alert_trans_ary = [
                    '%news_subject%'   => $news['subject'],
                ];

                $alert_service->success('news_del.success', $alert_trans_ary);
                $link_render->redirect($vr->get('news'), $pp->ary(), []);
            }

            $alert_service->error('news_del.error');
        }

        $menu_service->set('news');

        return $this->render('news/news_del.html.twig', [
            'form'      => $form->createView(),
            'news'      => $news,
            'schema'    => $pp->schema(),
        ]);
    }
}
