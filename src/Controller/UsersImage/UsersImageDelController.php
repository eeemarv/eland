<?php declare(strict_types=1);

namespace App\Controller\UsersImage;

use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class UsersImageDelController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        AlertService $alert_service,
        AccountRender $account_render,
        HeadingRender $heading_render,
        LinkRender $link_render,
        UserCacheService $user_cache_service,
        MenuService $menu_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        UsersImageDelAdminController $users_image_del_admin_controller,
        string $env_s3_url
    ):Response
    {
        if ($su->id() < 1)
        {
            $alert_service->error('Je hebt geen toegang tot deze actie');
            $link_render->redirect($vr->get('users'), $pp->ary(), []);
        }

        return $users_image_del_admin_controller(
            $request,
            $su->id(),
            $db,
            $alert_service,
            $account_render,
            $heading_render,
            $link_render,
            $user_cache_service,
            $pp,
            $vr,
            $menu_service,
            $env_s3_url
        );
    }
}
