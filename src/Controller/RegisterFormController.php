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
use App\Service\StaticContentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RegisterFormController extends AbstractController
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
        StaticContentService $static_content_service,
        AlertService $alert_service,
        PageParamsService $pp,
        LinkRender $link_render
    ):Response
    {
        if (!$config_service->get_bool('register_form.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Register form not enabled.');
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
            else if ($db->fetchOne('select c.user_id
                from ' . $pp->schema() . '.contact c, ' .
                    $pp->schema() . '.type_contact tc
                where c. value = ?
                    AND tc.id = c.id_type_contact
                    AND tc.abbrev = \'mail\'',
                    [$reg['email']], [\PDO::PARAM_STR]))
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
                    'register_form', $pp->schema(), 604800); // 1 week

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

        $out = $static_content_service->get('register_form', 'top', $pp->schema());

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="first_name" class="control-label">Voornaam*</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="first_name" name="first_name" ';
        $out .= 'value="';
        $out .= $reg['first_name'] ?? '';
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="last_name" class="control-label">Achternaam*</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="last_name" name="last_name" ';
        $out .= 'value="';
        $out .= $reg['last_name'] ?? '';
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="email" class="control-label">E-mail*</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-envelope-o"></i>';
        $out .= '</span>';
        $out .= '<input type="email" class="form-control" id="email" name="email" ';
        $out .= 'value="';
        $out .= $reg['email'] ?? '';
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="postcode" class="control-label">Postcode*</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-map-marker"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="postcode" name="postcode" ';
        $out .= 'value="';
        $out .= $reg['postcode'] ?? '';
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="gsm" class="control-label">Gsm</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-mobile"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="gsm" name="gsm" ';
        $out .= 'value="';
        $out .= $reg['gsm'] ?? '';
        $out .=  '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="tel" class="control-label">Telefoon</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-phone"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="tel" name="tel" ';
        $out .= 'value="';
        $out .= $reg['tel'] ?? '';
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $captcha_service->get_form_field();

        $out .= '<input type="submit" class="btn btn-primary btn-lg" value="Inschrijven" name="zend">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= $static_content_service->get('register_form', 'bottom', $pp->schema());

        $menu_service->set('register_form');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
