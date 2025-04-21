<?php declare(strict_types=1);

namespace App\Scheduler\Message;

use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(43200, jitter: 4000, transports: 'async')]
final class SyncUserCacheTaskMessage
{
    public function __invoke()
    {
    }
}