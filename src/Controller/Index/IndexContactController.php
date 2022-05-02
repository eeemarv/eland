<?php declare(strict_types=1);

namespace App\Controller\Index;

use App\Service\CaptchaService;
use App\Service\FormTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;

class IndexContactController extends AbstractController
{
    #[Route(
        '/contact',
        name: 'index_contact',
        methods: ['GET', 'POST'],
        priority: 40,
    )]

    public function __invoke(
        Request $request,
        RequestStack $request_stack,
        FormTokenService $form_token_service,
        CaptchaService $captcha_service,
        string $env_mail_hoster_address,
        string $env_mail_from_address,
        string $env_smtp_host,
        string $env_smtp_port,
        string $env_smtp_password,
        string $env_smtp_username
    ):Response
    {
        $errors = [];

        $mail = $request->request->get('mail', '');
        $message = $request->request->get('message', '');
        $form_ok = $request->query->get('form_ok', '');

        if ($request->isMethod('POST'))
        {
            $session = $request_stack->getSession();

            $to = $env_mail_hoster_address;
            $from = $env_mail_from_address;

            if (!$to || !$from)
            {
                throw new HttpException(500, 'Interne configuratie fout.');
            }

            if (!$captcha_service->validate())
            {
                $errors[] = 'De anti-spam verifiactiecode is niet juist ingevuld.';
            }

            $form_error = $form_token_service->get_error();

            if ($form_error)
            {
                $errors[] = $form_error;
            }

            if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
            {
                $errors[] = 'Geen geldig E-mail adres ingevuld.';
            }

            if (!$message)
            {
                $errors[] = 'Het bericht is leeg.';
            }

            if (!count($errors))
            {
                $text = $message . "\r\n\r\n\r\n" . 'browser: ';
                $text .= $request->headers->get('User-Agent') . "\n";
                $text .= 'form_token: ' . $form_token_service->get();

                $transport = (new \Swift_SmtpTransport($env_smtp_host, $env_smtp_port, 'tls'))
                    ->setUsername($env_smtp_username)
                    ->setPassword($env_smtp_password);
                $mailer = new \Swift_Mailer($transport);
                $mailer->registerPlugin(new \Swift_Plugins_AntiFloodPlugin(100, 30));

                $message = (new \Swift_Message())
                    ->setSubject('eLAND Contact Formulier')
                    ->setBody($text)
                    ->setTo($to)
                    ->setFrom($from)
                    ->setReplyTo($mail);

                $mailer->send($message);

                return $this->redirectToRoute('index_contact', ['form_ok' => '1']);
            }

            foreach ($errors as $error)
            {
                $session->getFlashBag()->add('alert', [
                    'type'      => 'error',
                    'message'	=> $error,
                ]);
            }
        }

        return $this->render('index/contact.html.twig', [
            'form_ok'       => $form_ok !== '',
            'mail'          => $mail,
            'message'       => $message,
            'form_token'    => $form_token_service->get(),
            'captcha'       => $captcha_service->get_form_field(),
        ]);
    }
}
