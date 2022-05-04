<?php declare(strict_types=1);

namespace App\Controller\ContactForm;

use App\Command\ContactForm\ContactFormCommand;
use App\Form\Type\ContactForm\ContactFormType;
use App\Queue\MailQueue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
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
        priority: 30,
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
        ConfigService $config_service,
        DataTokenService $data_token_service,
        PageParamsService $pp,
        MailQueue $mail_queue
    ):Response
    {
        if (!$config_service->get_bool('contact_form.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Contact form module not enabled.');
        }

        $support_email_addr = $config_service->get_ary('mail.addresses.support', $pp->schema());
        $mail_enabled = $config_service->get_bool('mail.enabled', $pp->schema());
        $form_disabled = !$mail_enabled || count($support_email_addr) < 1;

        $command = new ContactFormCommand();

        $form_options = [
            'validation_groups' => ['send'],
            'disabled'          => $form_disabled,
        ];

        $form = $this->createForm(ContactFormType::class, $command, $form_options);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid()
            && count($support_email_addr) > 0
            && $mail_enabled
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

            return $this->redirectToRoute('contact_form', $pp->ary());
        }

        if (!$mail_enabled)
        {
            $alert_service->warning('E-mail functies zijn
                uitgeschakeld door de beheerder.
                Je kan dit formulier niet gebruiken');
        }
        else if (count($support_email_addr) < 1)
        {
            $alert_service->warning('Er is geen support E-mail adres
                ingesteld door de beheerder.
                Je kan dit formulier niet gebruiken.');
        }

        return $this->render('contact_form/contact_form.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
