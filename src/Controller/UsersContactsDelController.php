<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\ContactsDelController;
use App\Render\AccountRender;
use Doctrine\DBAL\Connection as Db;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\ItemAccessService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;

class UsersContactsDelController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $contact_id,
        Db $db,
        AlertService $alert_service,
        UserCacheService $user_cache_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        ItemAccessService $item_access_service,
        HeadingRender $heading_render,
        AccountRender $account_render,
        SessionUserService $su,
        LinkRender $link_render
    ):Response
    {
        return ContactsDelController::form(
            $request,
            $su->id(),
            $contact_id,
            false,
            $db,
            $alert_service,
            $user_cache_service,
            $form_token_service,
            $menu_service,
            $item_access_service,
            $heading_render,
            $account_render,
            $link_render
        );
    }
}
