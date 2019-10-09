<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IndexContactController extends AbstractController
{
    public function index_contact(Request $request, app $app):Response
    {
        $mail = $request->request->get('mail', '');
        $message = $request->request->get('message', '');
        $form_ok = $request->query->get('form_ok', '');

        if ($request->isMethod('POST'))
        {
            $to = getenv('MAIL_HOSTER_ADDRESS');
            $from = getenv('MAIL_FROM_ADDRESS');

            if (!$to || !$from)
            {
                throw new HttpException(500, 'Interne configuratie fout.');
            }

            $errors = [];

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
                $text .= $app['request']->headers->get('User-Agent') . "\n";
                $text .= 'form_token: ' . $form_token_service->get();

                $enc = getenv('SMTP_ENC') ?: 'tls';
                $transport = (new \Swift_SmtpTransport(getenv('SMTP_HOST'), getenv('SMTP_PORT'), $enc))
                    ->setUsername(getenv('SMTP_USERNAME'))
                    ->setPassword(getenv('SMTP_PASSWORD'));
                $mailer = new \Swift_Mailer($transport);
                $mailer->registerPlugin(new \Swift_Plugins_AntiFloodPlugin(100, 30));

                $message = (new \Swift_Message())
                    ->setSubject('eLAND Contact Formulier')
                    ->setBody($text)
                    ->setTo($to)
                    ->setFrom($from)
                    ->setReplyTo($mail);

                $mailer->send($message);

                $link_render->redirect('index_contact', [], ['form_ok' => '1']);
            }

            foreach ($errors as $error)
            {
                $app['session']->getFlashBag()->add('alert', [
                    'type'      => 'error',
                    'message'	=> $error,
                ]);
            }
        }

        $menu_service->set('index_contact');

        return $this->render('index/contact.html.twig', [
            'form_ok'       => $form_ok !== '',
            'mail'          => $mail,
            'message'       => $message,
            'form_token'    => $form_token_service->get(),
            'captcha'       => $captcha_service->get_form_field(),
        ]);
    }
}
