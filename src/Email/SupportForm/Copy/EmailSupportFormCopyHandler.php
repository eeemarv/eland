<?php declare(strict_types=1);

namespace App\Email\SupportForm\Copy;

use App\Email\EmailDispatchMessage;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailSupportFormCopyHandler
{
    public function __construct(
      private readonly MessageBusInterface $bus,
      private readonly UserRepository $user_repository,
    ) {}

    public function __invoke(EmailSupportFormCopyMessage $message):void
    {
      $schema = $message->schema;
      $user_id = $message->user_id;

      $context = [
        'message' => $message->message,
        'user_id' => $message->user_id,
      ];

      $to = $this->user_repository->get_email_addresses_active_user($user_id, $schema);

      $m_dispatch = new EmailDispatchMessage(
        template: 'support_form/support_form_copy',
        message_class: get_class($message),
        context: $context,
        to: $to,
        schema: $schema,
      );

      $this->bus->dispatch($m_dispatch);
    }
}