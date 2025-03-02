<?php declare(strict_types=1);

namespace App\Controller\Index;

use App\Command\Index\IndexContactFormCommand;
use App\Form\Type\Index\IndexContactFormType;
use App\Service\DataTokenService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
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
        MailerInterface $mailer,
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

            $email_address = strtolower($command->email_address);
            $message = $command->message;

            $contact = [
                'message' 	    => $message,
                'email_address'	=> $email_address,
                'agent'		    => $request->headers->get('User-Agent'),
                'ip'		    => $request->getClientIp(),
            ];

            $token = $data_token_service->store($contact,
                'index_contact_form', null, 86400);

            $email = new TemplatedEmail();
            $email->from(new Address($env_mail_from_address, 'eLAND contact'));
            $email->to(new Address($email_address));
            $email->subject('Bevestig je bericht');
            $email->htmlTemplate('@mail/index/index_contact_confirm.html.twig');
            $email->context([
                'token' => $token,
            ]);

            $mailer->send($email);

            $alert_msg = 'Open je E-mailbox en klik
                de link aan die we je zonden om je
                bericht te bevestigen.';

            $this->addFlash('alert', [
                'type'      => 'success',
                'message'   => $alert_msg,
            ]);

            return $this->redirectToRoute('index_contact');
        }

        return $this->render('index/contact.html.twig', [
            'form'      => $form,
        ]);
    }
}
