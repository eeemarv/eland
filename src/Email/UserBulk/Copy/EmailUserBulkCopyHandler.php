<?php declare(strict_types=1);

namespace App\Email\UserBulk\Copy;

use App\Email\EmailDispatchMessage;
use App\Repository\UserRepository;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EmailUserBulkCopyHandler
{
  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly UserRepository $user_repository,
    private readonly HtmlSanitizerInterface $html_sanitizer,
  ) {}

  public function __invoke(EmailUserBulkCopyMessage $message):void
  {
    $sender_id = $message->sender_id;
    $user_ids_sent = $message->user_ids_sent;
    $user_ids_not_sent = $message->user_ids_not_sent;
    $schema = $message->schema;
    $sanitized_content = $this->html_sanitizer->sanitize($message->content);

    $context = [
      'sender_id' => $sender_id,
      'user_ids_sent'  => $user_ids_sent,
      'user_ids_not_sent'  => $user_ids_not_sent,
      'html_content'  => $sanitized_content,
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