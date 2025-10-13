<?php declare(strict_types=1);

namespace App\Email\MollieBulk\Copy;

use App\DTO\Schema;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('mail_hi')]
final class EmailMollieBulkCopyMessage
{
  public function __construct(
    public readonly int $to_user_id,
    public readonly array $payment_ids_sent,
    public readonly array $payment_ids_not_sent,
    public readonly string $message,
    public readonly string $subject,
    public readonly Schema $schema
  )
  {
  }
}