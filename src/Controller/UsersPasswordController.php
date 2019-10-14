<?php declare(strict_types=1);

namespace App\Controller;

use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\FormTokenService;
use App\Service\MailAddrSystemService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\PasswordStrengthService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class UsersPasswordController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        LinkRender $link_render,
        MailAddrSystemService $mail_addr_system_service,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        PasswordStrengthService $password_strength_service,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        UsersPasswordAdminController $users_password_admin_controller,
        MenuService $menu_service
    ):Response
    {
        return $users_password_admin_controller(
            $request,
            $su->id(),
            $db,
            $account_render,
            $alert_service,
            $assets_service,
            $form_token_service,
            $heading_render,
            $link_render,
            $mail_addr_system_service,
            $mail_addr_user_service,
            $mail_queue,
            $password_strength_service,
            $user_cache_service,
            $pp,
            $su,
            $vr,
            $menu_service
        );
    }
}
