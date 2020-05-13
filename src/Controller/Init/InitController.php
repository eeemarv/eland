<?php declare(strict_types=1);

namespace App\Controller\Init;

use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InitController extends AbstractController
{
    const POSSIBLE_IMAGE_EXT = ['jpg', 'jpeg', 'JPG', 'JPEG'];

    const ROUTES_LABELS = [
        'init_clear_users_cache'    => 'Clear users cache',
        'init_queue_geocoding'      => 'Queue geocoding',
    ];

    public function __invoke(
        Request $request,
        AlertService $alert_service,
        MenuService $menu_service,
        PageParamsService $pp,
        LinkRender $link_render,
        string $env_app_init_enabled
    ):Response
    {
        if (!$env_app_init_enabled)
        {
            throw new NotFoundHttpException('De init routes zijn niet ingeschakeld.');
        }

        $done = $request->query->get('ok', '');

        if ($done)
        {
            $alert_service->success('Done: ' . self::ROUTES_LABELS[$done]);
        }

        $out = '<div class="card fcard fcard-info">';
        $out .= '<div class="card-body">';
        $out .= '<h1>Init</h1>';

        $out .= '</div>';
        $out .= '<div class="card-body">';
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

        return $this->render('init/init.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
