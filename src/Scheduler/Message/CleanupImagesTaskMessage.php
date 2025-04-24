<?php declare(strict_types=1);

namespace App\Scheduler\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsMessage]
#[AsPeriodicTask(200, jitter: 60, transports: 'async')]
final class CleanupImagesTaskMessage
{
    public function __construct()
    {
    }
}