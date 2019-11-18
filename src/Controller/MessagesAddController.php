<?php declare(strict_types=1);

namespace App\Controller;

use App\HtmlProcess\HtmlPurifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Render\SelectRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\S3Service;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;

class MessagesAddController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        LoggerInterface $logger,
        AlertService $alert_service,
        AssetsService $assets_service,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        SelectRender $select_render,
        LinkRender $link_render,
        MenuService $menu_service,
        TypeaheadService $typeahead_service,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        HtmlPurifier $html_purifier,
        S3Service $s3_service,
        string $env_s3_url
    ):Response
    {
        $content = MessagesEditController::messages_form(
            $request,
            0,
            'add',
            $db,
            $logger,
            $alert_service,
            $assets_service,
            $config_service,
            $form_token_service,
            $heading_render,
            $intersystems_service,
            $item_access_service,
            $select_render,
            $link_render,
            $menu_service,
            $typeahead_service,
            $pp,
            $su,
            $vr,
            $user_cache_service,
            $html_purifier,
            $s3_service,
            $env_s3_url
        );

        return $this->render('base/navbar.html.twig', [
            'content'   => $content,
            'schema'    => $pp->schema(),
        ]);
    }
}
