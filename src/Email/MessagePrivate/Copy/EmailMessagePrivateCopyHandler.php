<?php declare(strict_types=1);

namespace App\Email\MessagePrivate\Copy;

use App\Email\EmailDispatchMessage;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailMessagePrivateCopyHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly MessageRepository $message_repository,
    private readonly UserRepository $user_repository,
  ) {}

  public function __invoke(EmailMessagePrivateCopyMessage $message):void
  {
    $sender_id = $message->sender_id;
    $sender_schema = $message->sender_schema;
    $message_id = $message->message_id;
    $schema = $message->schema;

    $ad_message = $this->message_repository->get(
      id: $message_id,
      schema: $schema,
    );

    if ($ad_message === false)
    {
      error_log('message ' . $message_id . ' not found');
      return;
    }

    $context = [
      'sender' => [
        'id'      => $sender_id,
        'schema'  => $sender_schema->str(),
      ],
      'message'   => $ad_message,
      'sender_message' => $message->sender_message,
    ];

    $to = $this->user_repository->get_email_addresses(
      user_id: $sender_id,
      schema: $sender_schema
    );

    $m_dispatch = new EmailDispatchMessage(
      template: 'message_private/message_private_copy',
      message_class: get_class($message),
      context: $context,
      to: $to,
      schema: $schema
    );

    $this->bus->dispatch($m_dispatch);
  }
}