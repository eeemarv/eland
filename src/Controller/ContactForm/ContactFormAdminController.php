<?php declare(strict_types=1);

namespace App\Controller\ContactForm;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\MenuService;
use App\Render\HeadingRender;
use App\Service\ConfigService;
use App\Service\PageParamsService;

class ContactFormAdminController extends AbstractController
{
    public function __invoke(
        MenuService $menu_service,
        ConfigService $config_service,
        HeadingRender $heading_render,
        PageParamsService $pp
    ):Response
    {
        $heading_render->add('Contact');
        $heading_render->fa('comment-o');

        // static_content top

        $out = '<div class="card fcard fcard-default">';
        $out .= '<div class="card-body">';

        $out .= '<p><i>Formulier niet actief ';
        $out .= 'in admin modus.</i></p>';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="mail">';
        $out .= 'Je E-mail Adres';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<i class="fa fa-envelope-o"></i>';
        $out .= '</span>';
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

        // captcha

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

        return $this->render('contact_form/contact_form_admin.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}