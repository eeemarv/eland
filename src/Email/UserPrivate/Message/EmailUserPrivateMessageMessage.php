<?php declare(strict_types=1);

namespace App\Email\UserPrivate\Message;

use App\DTO\Schema;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('mail_lo')]
final class EmailUserPrivateMessageMessage
{
  public function __construct(
    public readonly int $sender_id,
    public readonly Schema $sender_schema,
    public readonly string $message,
    public readonly int $user_id,
    public readonly Schema $schema
  )
  {
  }
}