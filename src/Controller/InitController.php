<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class InitController extends AbstractController
{
    const POSSIBLE_IMAGE_EXT = ['jpg', 'jpeg', 'JPG', 'JPEG'];

    const ROUTES_LABELS = [
        'init_elas_db_upgrade'      => 'eLAS database upgrade',
        'init_sync_users_images'    => 'Sync users images',
        'init_sync_messages_images' => 'Sync messages images',
        'init_clear_users_cache'    => 'Clear users cache',
        'init_empty_elas_tokens'    => 'Empty eLAS tokens',
        'init_empty_city_distance'  => 'Empty city distance table',
        'init_queue_geocoding'      => 'Queue geocoding',
        'init_copy_config'          => 'Copy config',
    ];

    public function __invoke(
        Request $request,
        AlertService $alert_service,
        MenuService $menu_service,
        PageParamsService $pp,
        LinkRender $link_render
    ):Response
    {
        $done = $request->query->get('ok', '');

        if ($done)
        {
            $alert_service->success('Done: ' . self::ROUTES_LABELS[$done]);
        }

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';
        $out .= '<h1>Init</h1>';

        $out .= '</div>';
        $out .= '<div class="panel-body">';
        $out .= '<div class="list-group">';

        foreach (self::ROUTES_LABELS as $route => $lbl)
        {
            $class_done = $done === $route ? ' list-group-item-success' : '';
            $out .= $link_render->link($route, $pp->ary(),
                [], $lbl, ['class' => 'list-group-item' . $class_done]);
        }
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('init');

        return $this->render('base/sidebar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
