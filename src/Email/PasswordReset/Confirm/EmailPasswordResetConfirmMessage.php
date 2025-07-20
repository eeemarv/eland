<?php declare(strict_types=1);

namespace App\Email\PasswordReset\Confirm;

use App\DTO\Schema;
use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Mime\Address;

#[AsMessage('mail_hi')]
final class EmailPasswordResetConfirmMessage
{
  public function __construct(
    public readonly Address $to,
    public readonly int $user_id,
    public readonly Schema $schema,
  )
  {
  }
}