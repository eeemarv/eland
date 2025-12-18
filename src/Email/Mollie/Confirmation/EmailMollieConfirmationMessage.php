<?php declare(strict_types=1);

namespace App\Email\Mollie\Confirmation;

use App\DTO\Schema;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('mail_lo')]
final class EmailMollieConfirmationMessage
{
  public function __construct(
    public readonly int $payment_id,
    public readonly Schema $schema
  )
  {
  }
}