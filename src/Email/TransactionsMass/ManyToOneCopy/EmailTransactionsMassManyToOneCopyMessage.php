<?php declare(strict_types=1);

namespace App\Email\TransactionsMass\ManyToOneCopy;

use App\DTO\Schema;
use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Uid\Uuid;

#[AsMessage('mail_hi')]
final class EmailTransactionsMassManyToOneCopyMessage
{
  public function __construct(
    public readonly Uuid $bulk_id,
    public readonly Schema $schema
  )
  {
  }
}