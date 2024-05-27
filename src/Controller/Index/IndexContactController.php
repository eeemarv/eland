<?php declare(strict_types=1);

namespace App\Controller\Index;

use App\Command\Index\IndexContactFormCommand;
use App\Form\Type\Index\IndexContactFormType;
use App\Service\AlertService;
use App\Service\DataTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
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
        DataTokenService $data_token_service,
        AlertService $alert_service,
        MailerInterface $mailer,
        #[Autowire('%env(MAIL_HOSTER_ADDRESS)%')]
        string $env_mail_hoster_address,
        #[Autowire('%env(MAIL_FROM_ADDRESS)%')]
        string $env_mail_from_address,
    ):Response
    {
        $command = new IndexContactFormCommand();

        $form_options = [
            'validation_groups' => ['send']
        ];

        $form = $this->createForm(IndexContactFormType::class, $command, $form_options);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid()
        )
        {
            $command = $form->getData();

            $email = strtolower($command->email);
            $message = $command->message;

            $contact = [
                'message' 	=> $message,
                'email'		=> $email,
                'agent'		=> $request->headers->get('User-Agent'),
                'ip'		=> $request->getClientIp(),
            ];

            $token = $data_token_service->store($contact,
                'index_contact_form', null, 86400);

            $text = $message . "\r\n\r\n\r\n" . 'browser: ';

            $mail = new Email();
            $mail->from($env_mail_from_address);
            $mail->to($email);
            $mail->text($text);
            $mail->subject('Bevestig je bericht, eLAND Contact Formulier');
            $mailer->send($mail);

            $alert_service->success('Open je E-mailbox en klik
                de link aan die we je zonden om je
                bericht te bevestigen.');

            return $this->redirectToRoute('index_contact_form');
        }

        /*

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
                 @var Session $session
                $session->getFlashBag()->add('alert', [
                    'type'      => 'error',
                    'message'	=> $error,
                ]);
            }
        }
        */

        return $this->render('index/contact.html.twig', [
            'form'      => $form,
        ]);
    }
}
