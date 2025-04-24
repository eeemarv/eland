<?php declare(strict_types=1);

namespace App\Scheduler\Handler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\Scheduler\Message\GeocodeTaskMessage;
use Symfony\Component\Lock\LockFactory;

#[AsMessageHandler]
final class GeocodeTaskHandler
{
    public function __construct(
        private readonly LockFactory $lock_factory
    )
    {

    }

    public function __invoke(GeocodeTaskMessage $geocode_task_message):void
    {
        $lock = $this->lock_factory->createLock('geocode');

        if (!$lock->acquire())
        {
            return;
        }

        try {
            error_log('geocode');

        } finally {
            $lock->release();
        }
    }
}