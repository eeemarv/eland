<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Security\User;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use App\Service\XdbService;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

class LoginAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        EncoderFactoryInterface $encoder_factory,
        XdbService $xdb_service,
        AlertService $alert_service,
        LoggerInterface $logger,
        MenuService $menu_service,
        LinkRender $link_render,
        HeadingRender $heading_render,
        ConfigService $config_service,
        AccountRender $account_render,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        string $env_master_password
    ):Response
    {

        $heading_render->add('Login');
        $heading_render->fa('sign-in');

        $out = '<div class="card bg-default">';
        $out .= '<div class="card-body">';

        $out .= '<p><i>Formulier niet actief ';
        $out .= 'in admin modus.</i></p>';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="login">';
        $out .= 'Login</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="login" name="login" ';
        $out .= 'value="" required disabled>';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'E-mail, Account Code of Gebruikersnaam';
        $out .= '</p>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="password">Paswoord</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<i class="fa fa-key"></i>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<input type="password" class="form-control" ';
        $out .= 'id="password" name="password" ';
        $out .= 'value="" required disabled>';
        $out .= '</div>';
        $out .= '<p>';
        $out .= $link_render->link_no_attr('password_reset_admin',
            $pp->ary(), [],
            'Klik hier als je je paswoord vergeten bent.');
        $out .= '</p>';
        $out .= '</div>';

        $out .= '<input type="submit" class="btn btn-info btn-lg" ';
        $out .= 'value="Inloggen" name="zend" disabled>';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('login');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
