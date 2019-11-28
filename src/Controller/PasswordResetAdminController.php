<?php declare(strict_types=1);

namespace App\Controller;

use App\Queue\MailQueue;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\DataTokenService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class PasswordResetAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        AlertService $alert_service,
        DataTokenService $data_token_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        MailQueue $mail_queue,
        LinkRender $link_render,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        $heading_render->add('Paswoord vergeten');
        $heading_render->fa('key');

        $out = '<div class="panel panel-default">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<p><i>Formulier niet actief ';
        $out .= 'in admin modus.</i></p>';

        $out .= '<div class="form-group">';
        $out .= '<label for="email" class="control-label">Je E-mail adres</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-envelope-o"></i>';
        $out .= '</span>';
        $out .= '<input type="email" class="form-control" id="email" name="email" ';
        $out .= 'value="" required disabled>';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'Vul hier het E-mail adres in waarmee je geregistreerd staat in het Systeem. ';
        $out .= 'Een link om je paswoord te resetten wordt naar je E-mailbox gestuurd.';
        $out .= '</p>';
        $out .= '</div>';

        $out .= '<input type="submit" class="btn btn-info btn-lg" ';
        $out .= 'value="Reset paswoord" name="zend" disabled>';
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
