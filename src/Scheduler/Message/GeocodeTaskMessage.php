<?php declare(strict_types=1);

namespace App\Scheduler\Message;

use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(610, jitter: 100, transports: 'async')]
final class GeocodeTaskMessage
{
    public function __invoke()
    {
    }
}