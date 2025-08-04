<?php declare(strict_types=1);

namespace App\Email\MessagePrivate\Message;

use App\Email\EmailDispatchMessage;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailMessagePrivateMessageHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly UserRepository $user_repository,
    private readonly MessageRepository $message_repository,
  ) {}

  public function __invoke(EmailMessagePrivateMessageMessage $message):void
  {
    $sender_id = $message->sender_id;
    $sender_schema = $message->sender_schema;
    $message_id = $message->message_id;
    $schema = $message->schema;

    $ad_message = $this->message_repository->get($message_id, $schema->str());

    $context = [
      'sender'  => [
        'id'    => $sender_id,
        'schema'  => $sender_schema->str(),
      ],
      'message' => $ad_message,
      'sender_message' => $message->sender_message,
    ];

    $to = $this->user_repository->get_email_addresses_active_user(
      user_id: $ad_message['user_id'],
      schema: $schema
    );

    $reply_to = $this->user_repository->get_email_addresses_active_user(
      user_id: $sender_id,
      schema: $sender_schema
    );

    $m_dispatch = new EmailDispatchMessage(
      template: 'message_private/message_private_message',
      message_class: get_class($message),
      context: $context,
      reply_to: $reply_to,
      to: $to,
      schema: $schema
    );

    $this->bus->dispatch($m_dispatch);
  }
}