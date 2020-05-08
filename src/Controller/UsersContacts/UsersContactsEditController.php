<?php declare(strict_types=1);

namespace App\Controller\UsersContacts;

use App\Controller\Contacts\ContactsEditAdminController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use App\Queue\GeocodeQueue;
use App\Render\AccountRender;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AssetsService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;

class UsersContactsEditController extends AbstractController
{
    public function __invoke(
        Request $request,
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
        SessionUserService $su,
        GeocodeQueue $geocode_queue
    ):Response
    {
        $content = ContactsEditAdminController::form(
            $request,
            $su->id(),
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

        return $this->render('base/navbar.html.twig', [
            'content'   => $content,
            'schema'    => $pp->schema(),
        ]);
    }
}
