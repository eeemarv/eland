<?php declare(strict_types=1);

namespace App\Email\SupportForm\Copy;

use App\DTO\Schema;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('mail_hi')]
final class EmailSupportFormCopyMessage
{
  public function __construct(
    public readonly int $user_id,
    public readonly string $message,
    public readonly Schema $schema,
  )
  {
  }
}