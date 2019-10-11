<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\ContactsAddController;
use App\Queue\GeocodeQueue;
use App\Render\AccountRender;
use Doctrine\DBAL\Connection as Db;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\TypeaheadService;

class UsersContactsAddController extends AbstractController
{
    public function users_contacts_add(
        Request $request,
        Db $db,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        ConfigService $config_service,
        LinkRender $link_render,
        AccountRender $account_render,
        AssetsService $assets_service,
        GeocodeQueue $geocode_queue,
        ItemAccessService $item_access_service,
        TypeaheadService $typeahead_service,
        HeadingRender $heading_render
    ):Response
    {
        return ContactsAddController::form(
            $request,
            $su->id(),
            false,
            $db,
            $alert_service,
            $form_token_service,
            $menu_service,
            $config_service,
            $link_render,
            $account_render,
            $assets_service,
            $geocode_queue,
            $item_access_service,
            $typeahead_service,
            $heading_render
        );
    }

    public function users_contacts_add_admin(
        Request $request,
        int $user_id,
        Db $db,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        ConfigService $config_service,
        LinkRender $link_render,
        AccountRender $account_render,
        AssetsService $assets_service,
        GeocodeQueue $geocode_queue,
        ItemAccessService $item_access_service,
        TypeaheadService $typeahead_service,
        HeadingRender $heading_render
    ):Response
    {
        return ContactsAddController::form(
            $request,
            $user_id,
            false,
            $db,
            $alert_service,
            $form_token_service,
            $menu_service,
            $config_service,
            $link_render,
            $account_render,
            $assets_service,
            $geocode_queue,
            $item_access_service,
            $typeahead_service,
            $heading_render
        );
    }
}
