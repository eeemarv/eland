<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Repository\NewsRepository;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class NewsExtendedController extends AbstractController
{
    public function __invoke(
        NewsRepository $news_repository,
        ConfigService $config_service,
        ItemAccessService $item_access_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        $visible_ary = $item_access_service->get_visible_ary_for_page();
        $event_at_asc_en = $config_service->get('news_order_asc', $pp->schema()) === '1' ? true : false;
        $news_items = $news_repository->get_all($event_at_asc_en, $visible_ary, $pp->schema());

        if($pp->is_admin())
        {
            $btn_top_render->add('news_add', $pp->ary(),
                [], 'Nieuws toevoegen');
        }

        $btn_nav_render->view('news_list', $pp->ary(),
            [], 'Lijst', 'align-justify', false);

        $btn_nav_render->view('news_extended', $pp->ary(),
            [], 'Lijst met omschrijvingen', 'th-list', true);

        $show_access = ($pp->is_user()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        $menu_service->set('news');

        return $this->render('news/news_extended.html.twig', [
            'news_items'    => $news_items,
            'show_access'   => $show_access,
            'schema'        => $pp->schema(),
        ]);
    }
}
