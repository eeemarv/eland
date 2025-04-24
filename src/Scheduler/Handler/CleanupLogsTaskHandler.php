<?php declare(strict_types=1);

namespace App\Scheduler\Handler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\Scheduler\Message\CleanupLogsTaskMessage;
use Symfony\Component\Lock\LockFactory;

#[AsMessageHandler]
final class CleanupLogsTaskHandler
{
    public function __construct(
        private readonly LockFactory $lock_factory
    )
    {
    }

    public function __invoke(
        CleanupLogsTaskMessage $cleanup_logs_task_message
    ):void
    {
        $lock = $this->lock_factory->createLock('cleanup_logs', 300);

        if (!$lock->acquire()){
            return;
        }

        try {
            error_log('cleanup_logs');

        } finally {
            $lock->release();
        }
    }
}