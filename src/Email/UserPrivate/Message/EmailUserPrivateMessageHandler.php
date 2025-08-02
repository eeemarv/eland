<?php declare(strict_types=1);

namespace App\Email\UserPrivate\Message;

use App\Email\EmailDispatchMessage;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailUserPrivateMessageHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly UserRepository $user_repository,
  ) {}

  public function __invoke(EmailUserPrivateMessageMessage $message):void
  {
    $sender_id = $message->sender_id;
    $sender_schema = $message->sender_schema;
    $user_id = $message->user_id;
    $schema = $message->schema;

    $context = [
      'sender'  => [
        'id'    => $sender_id,
        'schema'  => $sender_schema->str(),
      ],
      'user_id' => $user_id,
      'message' => $message->message,
    ];

    $to = $this->user_repository->get_email_addresses_active_user(
      user_id: $user_id,
      schema: $schema
    );

    $reply_to = $this->user_repository->get_email_addresses_active_user(
      user_id: $sender_id,
      schema: $sender_schema
    );

    $m_dispatch = new EmailDispatchMessage(
      template: 'user_private/user_private_message',
      message_class: get_class($message),
      context: $context,
      reply_to: $reply_to,
      to: $to,
      schema: $schema
    );

    $this->bus->dispatch($m_dispatch);
  }
}