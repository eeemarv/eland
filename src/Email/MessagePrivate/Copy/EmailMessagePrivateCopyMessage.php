<?php declare(strict_types=1);

namespace App\Email\MessagePrivate\Copy;

use App\DTO\Schema;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('mail_hi')]
final class EmailMessagePrivateCopyMessage
{
  public function __construct(
    public readonly int $message_id,
    public readonly int $sender_id,
    public readonly Schema $sender_schema,
    public readonly string $sender_message,
    public readonly Schema $schema
  )
  {
  }
}