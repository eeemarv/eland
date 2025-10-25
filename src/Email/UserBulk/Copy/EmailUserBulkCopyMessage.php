<?php declare(strict_types=1);

namespace App\Email\UserBulk\Copy;

use App\DTO\Schema;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('mail_hi')]
final class EmailUserBulkCopyMessage
{
  public function __construct(
    public readonly int $sender_id,
    public readonly array $user_ids_sent,
    public readonly array $user_ids_not_sent,
    public readonly string $content,
    public readonly string $subject,
    public readonly Schema $schema
  )
  {
  }
}