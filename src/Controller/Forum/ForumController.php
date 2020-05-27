<?php declare(strict_types=1);

namespace App\Controller\Forum;

use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Repository\ForumRepository;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ForumController extends AbstractController
{
    public function __invoke(
        Request $request,
        ForumRepository $forum_repository,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        // to do: filter after page loaded
        // $q = $request->query->get('q', '');

        $forum_topics = $forum_repository->get_visible_topics_with_reply_count($pp->schema());

        if ($pp->is_admin() || $pp->is_user())
        {
            $btn_top_render->add('forum_add_topic', $pp->ary(),
                [], 'Onderwerp toevoegen');
        }

        if ($pp->is_admin())
        {
            $btn_nav_render->csv();
        }

        $show_access = (!$pp->is_guest()
                && $config_service->get_intersystem_en($pp->schema()))
            || $pp->is_admin();

        $menu_service->set('forum');

        return $this->render('forum/forum_list.html.twig', [
            'forum_topics'  => $forum_topics,
            'show_access'   => $show_access,
            'schema'        => $pp->schema(),
        ]);
    }
}
