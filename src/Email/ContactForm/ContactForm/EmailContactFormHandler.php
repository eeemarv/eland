<?php declare(strict_types=1);

namespace App\Email\ContactForm\ContactForm;

use App\DTO\AddressAry;
use App\Email\EmailDispatchMessage;
use App\Service\ConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailContactFormHandler
{
    public function __construct(
      private readonly MessageBusInterface $bus,
      private readonly ConfigService $config_service,
    ) {}

    public function __invoke(EmailContactFormMessage $message):void
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

      $to = $this->config_service->get_ary('mail.addresses.support', $schema->str());

      $dispatch = new EmailDispatchMessage(
        template: 'contact_form/contact_form',
        context: $context,
        reply_to: $message->reply_to,
        to: New AddressAry($to),
        schema: $schema
      );

      $this->bus->dispatch($dispatch);
    }
}