<?php declare(strict_types=1);

namespace App\Email\SupportForm\Admin;

use App\DTO\Schema;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('mail_lo')]
final class EmailSupportFormAdminMessage
{
  public function __construct(
    public readonly string $message,
    public readonly int $user_id,
    public readonly Schema $schema
  )
  {
  }
}