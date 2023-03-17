<?php declare(strict_types=1);

namespace App\Controller\Index;

use App\Service\CaptchaService;
use App\Service\FormTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
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
        MailerInterface $mailer,
        #[Autowire('%env(MAIL_HOSTER_ADDRESS)%')]
        string $env_mail_hoster_address,
        #[Autowire('%env(MAIL_FROM_ADDRESS)%')]
        string $env_mail_from_address,
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

                $email = new Email();
                $email->from($from);
                $email->to($to);
                $email->text($text);
                $email->subject('eLAND Contact Formulier');
                $email->replyTo($mail);

                $mailer->send($email);

                return $this->redirectToRoute('index_contact', ['form_ok' => '1']);
            }

            foreach ($errors as $error)
            {
                /** @var Session $session */
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
