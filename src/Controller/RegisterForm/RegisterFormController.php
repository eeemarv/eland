<?php declare(strict_types=1);

namespace App\Controller\RegisterForm;

use App\Queue\MailQueue;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class RegisterFormController extends AbstractController
{
    #[Route(
        '/{system}/register',
        name: 'register_form',
        methods: ['GET', 'POST'],
        priority: 30,
        requirements: [
            'system'        => '%assert.system%',
        ],
        defaults: [
            'module'        => 'register_form',
        ],
    )]

    public function __invoke(
        Request $request,
        Db $db,
        LoggerInterface $logger,
        MailQueue $mail_queue,
        MenuService $menu_service,
        FormTokenService $form_token_service,
        DataTokenService $data_token_service,
        CaptchaService $captcha_service,
        ConfigService $config_service,
        AlertService $alert_service,
        PageParamsService $pp,
        LinkRender $link_render
    ):Response
    {
        $postcode_enabled = $config_service->get_bool('users.fields.postcode.enabled', $pp->schema());
        $errors = [];

        if (!$config_service->get_bool('register_form.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Register form not enabled.');
        }

        if ($request->isMethod('POST'))
        {
            $email = $request->request->get('email', '');
            $first_name = $request->request->get('first_name', '');
            $last_name = $request->request->get('last_name', '');
            $postcode = $request->request->get('postcode', '');
            $tel = $request->request->get('tel', '');
            $gsm = $request->request->get('gsm', '');

            $logger->info('Registration request for ' .
                $email, ['schema' => $pp->schema()]);

            if(!$email)
            {
                $errors[] = 'Vul een E-mail adres in.';
            }

            if (!$captcha_service->validate())
            {
                $errors[] = 'De anti-spam verifiactiecode is niet juist ingevuld.';
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            {
                $errors[] = 'Geen geldig E-mail adres.';
            }

            if (!count($errors)
                && $db->fetchOne('select c.user_id
                from ' . $pp->schema() . '.contact c, ' .
                    $pp->schema() . '.type_contact tc
                where c. value = ?
                    AND tc.id = c.id_type_contact
                    AND tc.abbrev = \'mail\'',
                    [$email], [\PDO::PARAM_STR]))
            {
                $errors[] = 'Er bestaat reeds een inschrijving
                    met dit E-mail adres.';
            }

            if (!$first_name)
            {
                $errors[] = 'Vul een Voornaam in.';
            }

            if (!$last_name)
            {
                $errors[] = 'Vul een Achternaam in.';
            }

            if ($postcode_enabled && !$postcode)
            {
                $errors[] = 'Vul een Postcode in.';
            }

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($errors))
            {
                $full_name = $first_name . ' ' . $last_name;

                $reg = [
                    'email'         => $email,
                    'first_name'    => $first_name,
                    'last_name'     => $last_name,
                    'full_name'     => $full_name,
                    'tel'           => $tel,
                    'gsm'           => $gsm,
                ];

                if ($postcode_enabled)
                {
                    $reg['postcode'] = $postcode;
                }

                $token = $data_token_service->store($reg,
                    'register_form', $pp->schema(), 604800); // 1 week

                $mail_queue->queue([
                    'schema'	=> $pp->schema(),
                    'to' 		=> [$email => $full_name],
                    'vars'		=> ['token' => $token],
                    'template'	=> 'register/confirm',
                ], 10000);

                $alert_service->success('Open je E-mailbox en klik op de
                    bevestigingslink in de E-mail die we naar je gestuurd
                    hebben om je inschrijving te voltooien.');

                $link_render->redirect('login', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        $out = '<div class="panel panel-info">';
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
        $out .= $first_name ?? '';
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
        $out .= $last_name ?? '';
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
        $out .= $email ?? '';
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        if ($postcode_enabled)
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="postcode" class="control-label">Postcode*</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<i class="fa fa-map-marker"></i>';
            $out .= '</span>';
            $out .= '<input type="text" class="form-control" id="postcode" name="postcode" ';
            $out .= 'value="';
            $out .= $postcode ?? '';
            $out .= '" required>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '<div class="form-group">';
        $out .= '<label for="gsm" class="control-label">Gsm</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-mobile"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="gsm" name="gsm" ';
        $out .= 'value="';
        $out .= $gsm ?? '';
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
        $out .= $tel ?? '';
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $captcha_service->get_form_field();

        $out .= '<input type="submit" class="btn btn-primary btn-lg" value="Inschrijven" name="zend">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('register_form');

        return $this->render('register_form/register_form.html.twig', [
            'content'   => $out,
        ]);
    }
}
