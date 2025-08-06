<?php declare(strict_types=1);

namespace App\Email\UserBulk\Copy;

use App\Email\EmailDispatchMessage;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailUserBulkCopyHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly UserRepository $user_repository,
  ) {}

  public function __invoke(EmailUserBulkCopyMessage $message):void
  {
    $sender_id = $message->sender_id;
    $user_ids = $message->user_ids;
    $omitted_user_ids = $message->omitted_user_ids;
    $schema = $message->schema;

    $context = [
      'sender_id' => $sender_id,
      'user_ids'  => $user_ids,
      'omitted_user_ids'  => $omitted_user_ids,
      'html_content'  => $message->message,
      'subject'   => $message->subject,
    ];

    $to = $this->user_repository->get_email_addresses(
      user_id: $sender_id,
      schema: $schema
    );

    $m_dispatch = new EmailDispatchMessage(
      template: 'user_bulk/user_bulk_copy',
      message_class: get_class($message),
      context: $context,
      to: $to,
      schema: $schema
    );

    $this->bus->dispatch($m_dispatch);
  }
}