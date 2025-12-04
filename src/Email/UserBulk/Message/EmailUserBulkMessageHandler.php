<?php declare(strict_types=1);

namespace App\Email\UserBulk\Message;

use App\Cnst\BulkCnst;
use App\Email\EmailDispatchMessage;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class EmailUserBulkMessageHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly UserRepository $user_repository,
  ) {}

  public function __invoke(EmailUserBulkMessageMessage $message):void
  {
    $sender_id = $message->sender_id;
    $user_ids = $message->user_ids;
    $schema = $message->schema;

    shuffle($user_ids);

    $reply_to = $this->user_repository->get_email_addresses(
      user_id: $sender_id,
      schema: $schema
    );

    $context = [
      'subject' => $message->subject,
    ];

    $bulk_id = Uuid::v4();

    foreach ($user_ids as $user_id)
    {
      $user = $this->user_repository->get(
        id: $user_id,
        schema: $schema,
      );

      $embedded_context = [];

      foreach (BulkCnst::USER_TPL_VARS as $tpl_key => $u_key)
      {
        $embedded_context[$tpl_key] = $user[$u_key];
      }

      $to = $this->user_repository->get_email_addresses(
        user_id: $user_id,
        schema: $schema,
        active_only: false,
      );

      $m_dispatch = new EmailDispatchMessage(
        template: 'user_bulk/user_bulk_message',
        message_class: get_class($message),
        context: $context,
        embedded_template: $message->content,
        embedded_context: $embedded_context,
        bulk_id: $bulk_id,
        bulk_created_by: $sender_id,
        reply_to: $reply_to,
        to: $to,
        schema: $schema
      );

      $this->bus->dispatch($m_dispatch);
    }
  }
}