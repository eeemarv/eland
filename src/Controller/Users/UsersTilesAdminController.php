<?php declare(strict_types=1);

namespace App\Controller\Users;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;

class UsersTilesAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        string $status,
        Db $db,
        HeadingRender $heading_render,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        AssetsService $assets_service,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp,
        VarRouteService $vr,
        MenuService $menu_service,
        UsersTilesController $users_tiles_controller,
        string $env_s3_url
    ):Response
    {
        return $users_tiles_controller(
            $request,
            $status,
            $db,
            $heading_render,
            $btn_nav_render,
            $btn_top_render,
            $assets_service,
            $link_render,
            $config_service,
            $pp,
            $vr,
            $menu_service,
            $env_s3_url
        );
    }
}
