<?php declare(strict_types=1);

namespace App\Controller;

use App\Queue\MailQueue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\CaptchaService;
use App\Service\ConfigService;
use App\Service\DataTokenService;
use App\Service\PageParamsService;
use Psr\Log\LoggerInterface;

class ContactFormAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        LoggerInterface $logger,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        ConfigService $config_service,
        CaptchaService $captcha_service,
        DataTokenService $data_token_service,
        LinkRender $link_render,
        HeadingRender $heading_render,
        PageParamsService $pp,
        MailQueue $mail_queue
    ):Response
    {
        $heading_render->add('Contact');
        $heading_render->fa('comment-o');

        // static_content top

        $out = '<div class="panel panel-default">';
        $out .= '<div class="panel-heading">';

        $out .= '<p><i>Formulier niet actief ';
        $out .= 'in admin modus.</i></p>';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="mail">';
        $out .= 'Je E-mail Adres';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-envelope-o"></i>';
        $out .= '</span>';
        $out .= '<input type="email" class="form-control" id="email" name="email" ';
        $out .= 'value="" required disabled>';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'Er wordt een validatielink die je moet ';
        $out .= 'aanklikken naar je E-mailbox verstuurd.';
        $out .= '</p>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="message">Je Bericht</label>';
        $out .= '<textarea name="message" id="message" ';
        $out .= 'class="form-control" rows="4" disabled>';
        $out .= '</textarea>';
        $out .= '</div>';

        $out .= $captcha_service->get_form_field(true);

        $out .= '<input type="submit" name="zend" disabled ';
        $out .= 'value="Verzenden" class="btn btn-info btn-lg">';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        //  static_content bottom

        $out .= '<p>Leden: indien mogelijk, login en ';
        $out .= 'gebruik het Support formulier. ';
        $out .= '<i>Als je je paswoord kwijt bent ';
        $out .= 'kan je altijd zelf een nieuw paswoord ';
        $out .= 'aanvragen met je E-mail adres ';
        $out .= 'vanuit de login-pagina!</i></p>';

        $menu_service->set('contact');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}