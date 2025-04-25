<?php declare(strict_types=1);

namespace App\Scheduler\Handler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\Scheduler\Message\CleanupMessagesTaskMessage;
use Symfony\Component\Lock\LockFactory;

#[AsMessageHandler]
final class CleanupMessagesTaskHandler
{
    public function __construct(
        private readonly LockFactory $lock_factory
    )
    {
    }

    public function __invoke(
        CleanupMessagesTaskMessage $cleanup_messages_task_message
    ):void
    {
        $lock = $this->lock_factory->createLock('cleanup_massages', 300);

        if (!$lock->acquire()){
            return;
        }

        try {
            error_log('cleanup_messages');

        } finally {
            $lock->release();
        }

    }
}