<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AssetsService;
use App\Service\CacheService;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class UsersMapAdminController extends AbstractController
{
    public function __invoke(
        string $status,
        Db $db,
        AccountRender $account_render,
        AssetsService $assets_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        HeadingRender $heading_render,
        CacheService $cache_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MenuService $menu_service,
        UsersMapController $users_map_controller,
        string $env_mapbox_token
    ):Response
    {
        return $users_map_controller(
            $status,
            $db,
            $account_render,
            $assets_service,
            $btn_nav_render,
            $btn_top_render,
            $heading_render,
            $cache_service,
            $item_access_service,
            $link_render,
            $config_service,
            $pp,
            $su,
            $vr,
            $menu_service,
            $env_mapbox_token
        );
    }
}
