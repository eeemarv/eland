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

    $context = [
      'token' => $message->token,
    ];

    $dispatch = new EmailDispatchMessage(
      template: 'contact_form/contact_form_confirm',
      context: $context,
      to: New AddressAry([$message->to]),
      schema: $schema,
    );

    $this->bus->dispatch($dispatch);
  }
}