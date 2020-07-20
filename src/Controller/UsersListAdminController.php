<?php declare(strict_types=1);

namespace App\Controller;

use App\HtmlProcess\HtmlPurifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\SelectRender;
use App\Repository\AccountRepository;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\CacheService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\ThumbprintAccountsService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class UsersListAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        string $status,
        Db $db,
        AccountRepository $account_repository,
        LoggerInterface $logger,
        SessionInterface $session,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        CacheService $cache_service,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        SelectRender $select_render,
        ThumbprintAccountsService $thumbprint_accounts_service,
        TypeaheadService $typeahead_service,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        UsersListController $users_list_controller,
        MenuService $menu_service,
        HtmlPurifier $html_purifier
    ):Response
    {
        return $users_list_controller(
            $request,
            $status,
            $db,
            $account_repository,
            $logger,
            $session,
            $account_render,
            $alert_service,
            $assets_service,
            $btn_nav_render,
            $btn_top_render,
            $cache_service,
            $config_service,
            $date_format_service,
            $form_token_service,
            $heading_render,
            $intersystems_service,
            $item_access_service,
            $link_render,
            $mail_addr_user_service,
            $mail_queue,
            $select_render,
            $thumbprint_accounts_service,
            $typeahead_service,
            $user_cache_service,
            $pp,
            $su,
            $vr,
            $menu_service,
            $html_purifier
        );
    }
}
