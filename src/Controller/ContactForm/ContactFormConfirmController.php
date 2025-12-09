<?php declare(strict_types=1);

namespace App\Controller\ContactForm;

use App\Email\ContactForm\Admin\EmailContactFormAdminMessage;
use App\Email\ContactForm\Confirm\EmailContactFormConfirmMessage;
use App\Email\ContactForm\Success\EmailContactFormSuccessMessage;
use App\Repository\EmailSentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[AsController]
class ContactFormConfirmController extends AbstractController
{
  #[Route(
    '/{system}/contact/{confirm_token}',
    name: 'contact_form_confirm',
    methods: ['GET'],
    priority: 10,
    requirements: [
      'confirm_token'   => '%uuid_base58%',
      'system'          => '%assert.system%',
    ],
    defaults: [
      'module'          => 'contact_form',
    ],
  )]

  public function __invoke(
    string $confirm_token,
    ConfigService $config_service,
    EmailSentRepository $email_sent_repository,
    PageParamsService $pp,
    MessageBusInterface $bus,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'contact_form.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('Contact form module not enabled.');
    }

    $uuid_confirm_token = Uuid::fromBase58($confirm_token);

    $is_not_found = false;
    $is_expired = false;
    $is_already_confirmed = false;
    $success = false;

    $record = $email_sent_repository->get_with_confirm_token(
      confirm_token: $uuid_confirm_token,
      minutes_exp: 60,
      schema: $pp->schema_o(),
    );

    if ($record === false)
    {
      $is_not_found = true;
    }
    else if ($record['message_class'] !== EmailContactFormConfirmMessage::class)
    {
      throw $this->createNotFoundException();
    }
    else if ($record['is_confirmed'])
    {
      $is_already_confirmed = true;
    }
    else if ($record['is_expired'])
    {
      $is_expired = true;
    }
    else
    {
      $success = true;

      $email_sent_repository->set_confirmed(
        confirm_token: $uuid_confirm_token,
        schema: $pp->schema_o(),
      );

      $data = $record['confirm_data'];

      $sender_email_address = new Address($data['email']);

      $m_contact = new EmailContactFormAdminMessage(
        reply_to: $sender_email_address,
        message: $data['message'],
        agent: $data['agent'],
        ip: $data['ip'],
        schema: $pp->schema_o(),
      );
      $bus->dispatch($m_contact);

      $m_success = new EmailContactFormSuccessMessage(
        to: $sender_email_address,
        message: $data['message'],
        schema: $pp->schema_o(),
      );
      $bus->dispatch($m_success);
    }

    return $this->render('contact_form/contact_form_confirm.html.twig', [
      'is_not_found'  => $is_not_found,
      'is_already_confirmed'  => $is_already_confirmed,
      'is_expired'    => $is_expired,
      'success'       => $success,
      'confirmed_at'  => $record['confirmed_at'] ?? null,
    ]);
  }
}
