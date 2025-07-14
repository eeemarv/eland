<?php declare(strict_types=1);

namespace App\Controller\ContactForm;

use App\Command\ContactForm\ContactFormCommand;
use App\Email\ContactForm\Confirm\EmailContactFormConfirmMessage;
use App\Form\Type\ContactForm\ContactFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\DataTokenService;
use App\Service\PageParamsService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ContactFormController extends AbstractController
{
  #[Route(
    '/{system}/contact',
    name: 'contact_form',
    methods: ['GET', 'POST'],
    priority: 30,
    requirements: [
      'system'  => '%assert.system%',
    ],
    defaults: [
      'module'  => 'contact_form',
    ],
  )]

  public function __invoke(
    Request $request,
    LoggerInterface $logger,
    ConfigService $config_service,
    DataTokenService $data_token_service,
    PageParamsService $pp,
    MessageBusInterface $bus,
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

      $m_confirm = new EmailContactFormConfirmMessage(
        to: new Address($email),
        schema: $pp->schema_o(),
        token: $token,
      );
      $bus->dispatch($m_confirm);

      $this->addFlash('success', 'Open je E-mailbox en klik
        de link aan die we je zonden om je
        bericht te bevestigen.');

      return $this->redirectToRoute('contact_form', $pp->ary());
    }

    if (!$mail_enabled)
    {
      $this->addFlash('warning', 'E-mail functies zijn
        uitgeschakeld door de beheerder.
        Je kan dit formulier niet gebruiken');
    }
    else if (count($support_email_addr) < 1)
    {
      $this->addFlash('warning', 'Er is geen support E-mail adres
        ingesteld door de beheerder.
        Je kan dit formulier niet gebruiken.');
    }

    return $this->render('contact_form/contact_form.html.twig', [
      'form'   => $form->createView(),
    ]);
  }
}
