<?php declare(strict_types=1);

namespace App\Email\ContactForm\Confirm;

use App\DTO\AddressAry;
use App\Email\EmailDispatchMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailContactFormConfirmHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
  ) {}

  public function __invoke(EmailContactFormConfirmMessage $message):void
  {
    $schema = $message->schema;

    $confirm_data = [
      'message' => $message->message,
      'email'   => $message->to->getAddress(),
      'ip'      => $message->ip,
      'agent'   => $message->agent
    ];

    $m_dispatch = new EmailDispatchMessage(
      template: 'contact_form/contact_form_confirm',
      message_class: get_class($message),
      to: New AddressAry([$message->to]),
      add_confirm_token: true,
      confirm_data: $confirm_data,
      schema: $schema,
    );

    $this->bus->dispatch($m_dispatch);
  }
}