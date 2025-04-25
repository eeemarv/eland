<?php declare(strict_types=1);

namespace App\Scheduler\Handler;

use App\Scheduler\Message\CheckPeriodicOverviewTaskMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Lock\LockFactory;

#[AsMessageHandler]
final class CheckPeriodOverviewTaskHandler
{
    public function __construct(
        private readonly LockFactory $lock_factory
    )
    {
    }

    public function __invoke(
        CheckPeriodicOverviewTaskMessage $check_periodic_overview_task_message
    ):void
    {
        $lock = $this->lock_factory->createLock('check_periodic_mail', 300);

        if (!$lock->acquire()){
            return;
        }

        try {
            error_log('check_periodic_overview');

        } finally {
            $lock->release();
        }
    }
}