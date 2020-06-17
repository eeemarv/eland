<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Controller\Contacts\ContactsUserShowInlineController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\DistanceService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;

class UsersShowController extends AbstractController
{
    public function __invoke(
        Request $request,
        string $status,
        int $id,
        Db $db,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        DateFormatService $date_format_service,
        UserCacheService $user_cache_service,
        DistanceService $distance_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MenuService $menu_service,
        ContactsUserShowInlineController $contacts_user_show_inline_controller,
        UsersShowAdminController $users_show_admin_controller,
        string $env_s3_url,
        string $env_map_access_token,
        string $env_map_tiles_url
    ):Response
    {
        return $users_show_admin_controller(
            $request,
            $status,
            $id,
            $db,
            $account_render,
            $alert_service,
            $assets_service,
            $btn_nav_render,
            $btn_top_render,
            $config_service,
            $form_token_service,
            $heading_render,
            $item_access_service,
            $link_render,
            $mail_addr_user_service,
            $mail_queue,
            $date_format_service,
            $user_cache_service,
            $distance_service,
            $pp,
            $su,
            $vr,
            $menu_service,
            $contacts_user_show_inline_controller,
            $env_s3_url,
            $env_map_access_token,
            $env_map_tiles_url
        );
    }
}
