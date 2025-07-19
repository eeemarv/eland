<?php declare(strict_types=1);

namespace App\Email\ContactForm\Confirm;

use App\DTO\Schema;
use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Mime\Address;

#[AsMessage('mail_hi')]
final class EmailContactFormConfirmMessage
{
  public function __construct(
    public readonly Address $to,
    public readonly string $message,
    public readonly string $ip,
    public readonly string $agent,
    public readonly Schema $schema,
  )
  {
  }
}