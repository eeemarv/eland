<?php declare(strict_types=1);

namespace App\Controller\UsersContacts;

use App\Controller\Contacts\ContactsAddAdminController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;

class UsersContactsAddController extends AbstractController
{
    public function __invoke(
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
        PageParamsService $pp,
        SessionUserService $su,
        HeadingRender $heading_render
    ):Response
    {
        $content = ContactsAddAdminController::form(
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
            $pp,
            $su,
            $heading_render
        );

        return $this->render('base/navbar.html.twig', [
            'content'   => $content,
            'schema'    => $pp->schema(),
        ]);
    }
}
