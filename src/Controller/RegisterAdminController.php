<?php declare(strict_types=1);

namespace App\Controller;

use App\Queue\MailQueue;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\CaptchaService;
use App\Service\ConfigService;
use App\Service\DataTokenService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class RegisterAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        LoggerInterface $logger,
        MailQueue $mail_queue,
        MenuService $menu_service,
        HeadingRender $heading_render,
        FormTokenService $form_token_service,
        DataTokenService $data_token_service,
        CaptchaService $captcha_service,
        ConfigService $config_service,
        AlertService $alert_service,
        PageParamsService $pp,
        LinkRender $link_render
    ):Response
    {
        $heading_render->add('Inschrijven');
        $heading_render->fa('check-square-o');

        $top_text = $config_service->get('registration_top_text', $pp->schema());

        $out = $top_text ?: '';

        $out .= '<div class="panel panel-default">';
        $out .= '<div class="panel-heading">';

        $out .= '<p><i>Formulier niet actief ';
        $out .= 'in admin modus.</i></p>';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="first_name" class="control-label">Voornaam*</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="first_name" name="first_name" ';
        $out .= 'value="" required disabled>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="last_name" class="control-label">Achternaam*</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="last_name" name="last_name" ';
        $out .= 'value="" required disabled>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="email" class="control-label">E-mail*</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<i class="fa fa-envelope-o"></i>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<input type="email" class="form-control" id="email" name="email" ';
        $out .= 'value="" required disabled>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="postcode" class="control-label">Postcode*</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<i class="fa fa-map-marker"></i>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="postcode" name="postcode" ';
        $out .= 'value="" required disabled>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="gsm" class="control-label">Gsm</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<i class="fa fa-mobile"></i>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="gsm" name="gsm" ';
        $out .= 'value="" disabled>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="tel" class="control-label">Telefoon</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<i class="fa fa-phone"></i>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="tel" name="tel" ';
        $out .= 'value="" disabled>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $captcha_service->get_form_field(true);

        $out .= '<input type="submit" class="btn btn-primary btn-lg" ';
        $out .= 'value="Inschrijven" name="zend" disabled>';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $bottom_text = $config_service->get('registration_bottom_text', $pp->schema());

        $out .= $bottom_text ?: '';

        $menu_service->set('register');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
