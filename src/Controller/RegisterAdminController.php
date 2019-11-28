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
        if (!$config_service->get('registration_en', $pp->schema()))
        {
            $alert_service->warning('De inschrijvingspagina is niet ingeschakeld.');
            $link_render->redirect('login', $pp->ary(), []);
        }

        if ($request->isMethod('POST'))
        {
            $reg = [
                'email'			=> $request->request->get('email', ''),
                'first_name'	=> $request->request->get('first_name', ''),
                'last_name'		=> $request->request->get('last_name', ''),
                'postcode'		=> $request->request->get('postcode', ''),
                'tel'			=> $request->request->get('tel', ''),
                'gsm'			=> $request->request->get('gsm', ''),
            ];

            $logger->info('Registration request for ' .
                $reg['email'], ['schema' => $pp->schema()]);

            if(!$reg['email'])
            {
                $alert_service->error('Vul een E-mail adres in.');
            }
            else if (!$captcha_service->validate())
            {
                $alert_service->error('De anti-spam verifiactiecode is niet juist ingevuld.');
            }
            else if (!filter_var($reg['email'], FILTER_VALIDATE_EMAIL))
            {
                $alert_service->error('Geen geldig E-mail adres.');
            }
            else if ($db->fetchColumn('select c.id_user
                from ' . $pp->schema() . '.contact c, ' .
                    $pp->schema() . '.type_contact tc
                where c. value = ?
                    AND tc.id = c.id_type_contact
                    AND tc.abbrev = \'mail\'', [$reg['email']]))
            {
                $alert_service->error('Er bestaat reeds een inschrijving
                    met dit E-mail adres.');
            }
            else if (!$reg['first_name'])
            {
                $alert_service->error('Vul een Voornaam in.');
            }
            else if (!$reg['last_name'])
            {
                $alert_service->error('Vul een Achternaam in.');
            }
            else if (!$reg['postcode'])
            {
                $alert_service->error('Vul een Postcode in.');
            }
            else if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
            }
            else
            {
                $token = $data_token_service->store($reg,
                    'register', $pp->schema(), 604800); // 1 week

                $mail_queue->queue([
                    'schema'	=> $pp->schema(),
                    'to' 		=> [$reg['email'] => $reg['first_name'] . ' ' . $reg['last_name']],
                    'vars'		=> ['token' => $token],
                    'template'	=> 'register/confirm',
                ], 10000);

                $alert_service->success('Open je E-mailbox en klik op de
                    bevestigingslink in de E-mail die we naar je gestuurd
                    hebben om je inschrijving te voltooien.');

                $link_render->redirect('login', $pp->ary(), []);
            }
        }

        $heading_render->add('Inschrijven');
        $heading_render->fa('check-square-o');

        $top_text = $config_service->get('registration_top_text', $pp->schema());

        $out = $top_text ?: '';

        $out .= '<div class="panel panel-default">';
        $out .= '<div class="panel-heading">';

        $out .= '<p>Dit formulier is enkel illustratief ';
        $out .= 'in de admin modus.</p>';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="first_name" class="control-label">Voornaam*</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="first_name" name="first_name" ';
        $out .= 'value="" required disabled>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="last_name" class="control-label">Achternaam*</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="last_name" name="last_name" ';
        $out .= 'value="" required disabled>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="email" class="control-label">E-mail*</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-envelope-o"></i>';
        $out .= '</span>';
        $out .= '<input type="email" class="form-control" id="email" name="email" ';
        $out .= 'value="" required disabled>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="postcode" class="control-label">Postcode*</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-map-marker"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="postcode" name="postcode" ';
        $out .= 'value="" required disabled>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="gsm" class="control-label">Gsm</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-mobile"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="gsm" name="gsm" ';
        $out .= 'value="" disabled>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="tel" class="control-label">Telefoon</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-phone"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="tel" name="tel" ';
        $out .= 'value="" disabled>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $captcha_service->get_form_field(true);

        $out .= '<input type="submit" class="btn btn-primary btn-lg" ';
        $out .= 'value="Inschrijven" name="zend" disabled>';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $bottom_text = $config_service->get('registration_bottom_text', $pp->schema());

        $out .= $bottom_text ?: '';

        $menu_service->set('register');

        return $this->render('base/sidebar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
