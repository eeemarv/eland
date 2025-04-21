<?php declare(strict_types=1);

namespace App\Scheduler\Message;

use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(86400, jitter: 4000, transports: 'async')]
final class CleanupLogsTaskMessage
{
    public function __invoke()
    {
    }
}