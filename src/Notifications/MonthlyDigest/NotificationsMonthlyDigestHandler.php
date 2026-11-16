<?php declare(strict_types=1);

namespace App\Notifications\MonthlyDigest;

use App\DTO\Schema;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler()]
class NotificationsMonthlyDigestHandler
{
  public function __construct(
    private readonly LockFactory $lock_factory,
  )
  {
  }

  public function __invoke(
    NotificationsMonthlyDigestMessage $message,
  ):void
  {
    $schema = $message->schema;
    $lock_name = $schema->str() . ':notifications_monthly_digest';
    $lock = $this->lock_factory->createLock(
      resource: $lock_name, 
      ttl: 300,
    );

    if (!$lock->acquire()) 
    {
      return;
    }

    try 
    {
      // 
    } 
    finally 
    {
      $lock->release();
    }
  }
}
