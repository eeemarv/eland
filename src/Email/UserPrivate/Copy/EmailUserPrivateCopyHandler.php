<?php declare(strict_types=1);

namespace App\Email\UserPrivate\Copy;

use App\Email\EmailDispatchMessage;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailUserPrivateCopyHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly UserRepository $user_repository,
  ) {}

  public function __invoke(EmailUserPrivateCopyMessage $message):void
  {
    $sender_id = $message->sender_id;
    $sender_schema = $message->sender_schema;
    $user_id = $message->user_id;
    $schema = $message->schema;

    $context = [
      'sender' => [
        'id'      => $sender_id,
        'schema'  => $sender_schema->str(),
      ],
      'user_id' => $user_id,
      'message' => $message->message,
    ];

    $to = $this->user_repository->get_email_addresses(
      user_id: $sender_id,
      schema: $sender_schema
    );

    $m_dispatch = new EmailDispatchMessage(
      template: 'user_private/user_private_copy',
      message_class: get_class($message),
      context: $context,
      to: $to,
      schema: $schema
    );

    $this->bus->dispatch($m_dispatch);
  }
}