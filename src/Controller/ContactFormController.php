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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ContactFormController extends AbstractController
{
    #[Route(
        '/{system}/contact',
        name: 'contact_form',
        methods: ['GET', 'POST'],
        priority: 10,
        requirements: [
            'system'        => '%assert.system%',
        ],
        defaults: [
            'module'        => 'contact_form',
        ],
    )]

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
        if (!$config_service->get_bool('contact_form.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Contact form module not enabled.');
        }

        $errors = [];

        if($request->isMethod('POST'))
        {
            if (!$captcha_service->validate())
            {
                $errors[] = 'De anti-spam verifiactiecode is niet juist ingevuld.';
            }

            $email = strtolower($request->request->get('email'));
            $message = $request->request->get('message');

            if (empty($email) || !$email)
            {
                $errors[] = 'Vul je E-mail adres in';
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            {
                $errors[] = 'Geen geldig E-mail adres';
            }

            if (empty($message) || strip_tags($message) == '' || !$message)
            {
                $errors[] = 'Geef een bericht in.';
            }

            if (!$config_service->get_ary('mail.addresses.support', $pp->schema()))
            {
                $errors[] = 'Het Support E-mail adres is niet ingesteld in dit Systeem';
            }

            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if(!count($errors))
            {
                $contact = [
                    'message' 	=> $message,
                    'email'		=> $email,
                    'agent'		=> $request->headers->get('User-Agent'),
                    'ip'		=> $request->getClientIp(),
                ];

                $token = $data_token_service->store($contact,
                    'contact_form', $pp->schema(), 86400);

                $logger->info('Contact form filled in with address ' .
                    $email . ' ' .
                    json_encode($contact),
                    ['schema' => $pp->schema()]);

                $mail_queue->queue([
                    'schema'	=> $pp->schema(),
                    'to' 		=> [
                        $email => $email
                    ],
                    'template'	=> 'contact/confirm',
                    'vars'		=> [
                        'token' 	=> $token,
                    ],
                ], 10000);

                $alert_service->success('Open je E-mailbox en klik
                    de link aan die we je zonden om je
                    bericht te bevestigen.');

                $link_render->redirect('contact_form', $pp->ary(), []);
            }
            else
            {
                $alert_service->error($errors);
            }
        }
        else
        {
            $message = '';
            $email = '';
        }

        $form_disabled = false;

        if (!$config_service->get('mailenabled', $pp->schema()))
        {
            $alert_service->warning('E-mail functies zijn
                uitgeschakeld door de beheerder.
                Je kan dit formulier niet gebruiken');

            $form_disabled = true;
        }
        else if (!$config_service->get_ary('mail.addresses.support', $pp->schema()))
        {
            $alert_service->warning('Er is geen support E-mail adres
                ingesteld door de beheerder.
                Je kan dit formulier niet gebruiken.');

            $form_disabled = true;
        }

        $heading_render->add('Contact');
        $heading_render->fa('comment-o');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

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
        $out .= 'value="';
        $out .= $email;
        $out .= '" required';
        $out .= $form_disabled ? ' disabled' : '';
        $out .= '>';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'Er wordt een validatielink die je moet ';
        $out .= 'aanklikken naar je E-mailbox verstuurd.';
        $out .= '</p>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="message">Je Bericht</label>';
        $out .= '<textarea name="message" id="message" ';
        $out .= $form_disabled ? 'disabled ' : '';
        $out .= 'class="form-control" rows="4">';
        $out .= $message;
        $out .= '</textarea>';
        $out .= '</div>';

        $out .= $captcha_service->get_form_field();

        $out .= '<input type="submit" name="zend" ';
        $out .= $form_disabled ? 'disabled ' : '';
        $out .= 'value="Verzenden" class="btn btn-info btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('contact_form');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
