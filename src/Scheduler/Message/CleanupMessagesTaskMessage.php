<?php declare(strict_types=1);

namespace App\Scheduler\Message;

use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(43200, jitter: 6000, transports: 'async')]
final class CleanupMessagesTaskMessage
{
    public function __invoke()
    {
    }
}