<?php declare(strict_types=1);

namespace App\Email\ContactForm\Success;

use App\DTO\AddressAry;
use App\Email\EmailDispatchMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailContactFormSuccessHandler
{
    public function __construct(
      private readonly MessageBusInterface $bus,
    ) {}

    public function __invoke(EmailContactFormSuccessMessage $message):void
    {
      $schema = $message->schema;

      $context = [
        'message' => $message->message,
      ];

      $dispatch = new EmailDispatchMessage(
        template: 'contact_form/contact_form_success',
        context: $context,
        to: New AddressAry([$message->to]),
        schema: $schema,
      );

      $this->bus->dispatch($dispatch);
    }
}