<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\ContactsEditController;
use Doctrine\DBAL\Connection as Db;
use App\Queue\GeocodeQueue;
use App\Render\AccountRender;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\assetsService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;

class UsersContactsEditAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $user_id,
        int $contact_id,
        Db $db,
        FormTokenService $form_token_service,
        AlertService $alert_service,
        AssetsService $assets_service,
        MenuService $menu_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        HeadingRender $heading_render,
        AccountRender $account_render,
        PageParamsService $pp,
        GeocodeQueue $geocode_queue
    ):Response
    {
        return ContactsEditController::form(
            $request,
            $user_id,
            $contact_id,
            false,
            $db,
            $form_token_service,
            $alert_service,
            $assets_service,
            $menu_service,
            $item_access_service,
            $link_render,
            $heading_render,
            $account_render,
            $pp,
            $geocode_queue
        );
    }
}