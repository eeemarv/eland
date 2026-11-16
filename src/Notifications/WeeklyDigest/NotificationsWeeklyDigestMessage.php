<?php declare(strict_types=1);

namespace App\Notifications\WeeklyDigest;

use App\DTO\Schema;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: 'async')]
class NotificationsWeeklyDigestMessage
{
  public function __construct(
    public readonly Schema $schema,
  )
  {
  }

  public function __toString()
  {
    return self::class . ':' . $this->schema->str();
  }
}
