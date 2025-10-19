<?php declare(strict_types=1);

namespace App\Controller\ContactForm;

use App\Command\ContactForm\ContactFormCommand;
use App\Email\ContactForm\Confirm\EmailContactFormConfirmMessage;
use App\Form\Type\ContactForm\ContactFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
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
    ConfigService $config_service,
    PageParamsService $pp,
    MessageBusInterface $bus,
  ):Response
  {
    if (!$config_service->get_bool('contact_form.enabled', $pp->schema()))
    {
      throw $this->createNotFoundException('Contact form module not enabled.');
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

      $m_confirm = new EmailContactFormConfirmMessage(
        to: new Address($email),
        message: $message,
        ip: $request->getClientIp(),
        agent: $request->headers->get('User-Agent'),
        schema: $pp->schema_o(),
      );
      $bus->dispatch($m_confirm);

      $this->addFlash('content', 'link_sent');

      return $this->redirectToRoute('contact_form_link_sent', $pp->ary());
    }

    if (!$mail_enabled)
    {
      $this->addFlash('warning', [
        'key' => 'flash.email.functions_disabled',
      ]);
    }
    else if (count($support_email_addr) < 1)
    {
      $this->addFlash('warning', [
        'key' => 'flash.email.no_support_config',
      ]);
    }

    return $this->render('contact_form/contact_form.html.twig', [
      'form'  => $form->createView(),
    ]);
  }
}
