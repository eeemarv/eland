<?php declare(strict_types=1);

namespace App\Email\ContactForm\Admin;

use App\DTO\AddressAry;
use App\Email\EmailDispatchMessage;
use App\Service\ConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;

#[AsMessageHandler]
final class EmailContactFormAdminHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly ConfigService $config_service,
  ) {}

  public function __invoke(EmailContactFormAdminMessage $message):void
  {
    $schema = $message->schema;

    $context = [
      'message' => $message->message,
      'sender'  => [
        'email' => $message->reply_to->toString(),
        'agent' => $message->agent,
        'ip' => $message->ip,
      ],
    ];

    $to_email_ary = $this->config_service->get_ary('mail.addresses.support', $schema->str());
    $to = array_map(fn($e) => new Address($e), $to_email_ary);

    $dispatch = new EmailDispatchMessage(
      template: 'contact_form/contact_form_admin',
      message_class: get_class($message),
      context: $context,
      reply_to: $message->reply_to,
      to: New AddressAry($to),
      schema: $schema
    );

    $this->bus->dispatch($dispatch);
  }
}