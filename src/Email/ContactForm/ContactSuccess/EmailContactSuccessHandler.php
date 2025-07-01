<?php declare(strict_types=1);

namespace App\Email\ContactForm\ContactSuccess;

use App\DTO\AddressAry;
use App\Email\EmailDispatchMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailContactSuccessHandler
{
    public function __construct(
      private readonly MessageBusInterface $bus,
    ) {}

    public function __invoke(EmailContactSuccessMessage $message):void
    {
      $schema = $message->schema;

      $context = [
        'message' => $message->message,
      ];

      $dispatch = new EmailDispatchMessage(
        template: 'contact_form/contact_success',
        context: $context,
        to: New AddressAry([$message->to]),
        schema: $schema,
      );

      $this->bus->dispatch($dispatch);
    }
}