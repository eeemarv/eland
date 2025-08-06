<?php declare(strict_types=1);

namespace App\Email\UserBulk\Message;

use App\DTO\Schema;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('mail_lo')]
final class EmailUserBulkMessageMessage
{
  public function __construct(
    public readonly int $sender_id,
    public readonly array $user_ids,
    public readonly string $message,
    public readonly string $subject,
    public readonly Schema $schema
  )
  {
  }
}