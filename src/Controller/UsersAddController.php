<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\UsersEditAdminController;
use App\Queue\GeocodeQueue;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Render\SelectRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\MailAddrSystemService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\PasswordStrengthService;
use App\Service\SessionUserService;
use App\Service\SystemsService;
use App\Service\ThumbprintAccountsService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use App\Service\XdbService;
use Doctrine\DBAL\Connection as Db;

class UsersAddController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        FormTokenService $form_token_service,
        GeocodeQueue $geocode_queue,
        HeadingRender $heading_render,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        PasswordStrengthService $password_strength_service,
        SelectRender $select_render,
        SystemsService $systems_service,
        TypeaheadService $typeahead_service,
        UserCacheService $user_cache_service,
        XdbService $xdb_service,
        ThumbprintAccountsService $thumbprint_accounts_service,
        MailAddrUserService $mail_addr_user_service,
        MailAddrSystemService $mail_addr_system_service,
        MailQueue $mail_queue,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MenuService $menu_service
    ):Response
    {
        return UsersEditAdminController::form(
            $request,
            0,
            false,
            $db,
            $account_render,
            $alert_service,
            $assets_service,
            $config_service,
            $date_format_service,
            $form_token_service,
            $geocode_queue,
            $heading_render,
            $intersystems_service,
            $item_access_service,
            $link_render,
            $password_strength_service,
            $select_render,
            $systems_service,
            $typeahead_service,
            $user_cache_service,
            $xdb_service,
            $thumbprint_accounts_service,
            $mail_addr_user_service,
            $mail_addr_system_service,
            $mail_queue,
            $pp,
            $su,
            $vr,
            $menu_service
        );
    }
}
