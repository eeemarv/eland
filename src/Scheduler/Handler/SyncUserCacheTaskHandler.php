<?php declare(strict_types=1);

namespace App\Scheduler\Handler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\Scheduler\Message\SyncUserCacheTaskMessage;
use Symfony\Component\Lock\LockFactory;

#[AsMessageHandler]
final class SyncUserCacheTaskHandler
{
    public function __construct(
        private readonly LockFactory $lock_factory
    )
    {
    }

    public function __invoke(
        SyncUserCacheTaskMessage $sync_user_cache_task_message
    ):void
    {
        $lock = $this->lock_factory->createLock('sync_user_cache');

        if (!$lock->acquire())
        {
            return;
        }

        try {
            error_log('sync_user_cache');




        } finally {
            $lock->release();
        }
    }
}