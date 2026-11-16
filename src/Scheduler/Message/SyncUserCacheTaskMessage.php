<?php declare(strict_types=1);

namespace App\Scheduler\Message;

use Symfony\Component\Scheduler\Attribute\AsCronTask;


final class SyncUserCacheTaskMessage
{
  public function __invoke()
  {
  }
}