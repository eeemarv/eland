<?php declare(strict_types=1);

namespace App\Scheduler\Message;

use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(1800, jitter: 600, transports: 'async')]
final class CheckPeriodicOverviewTaskMessage
{
    public function __invoke()
    {
    }
}