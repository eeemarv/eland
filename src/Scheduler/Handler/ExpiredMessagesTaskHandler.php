<?php declare(strict_types=1);

namespace App\Scheduler\Handler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\Scheduler\Message\ExpiredMessagesTaskMessage;
use Symfony\Component\Lock\LockFactory;

#[AsMessageHandler]
final class ExpiredMessagesTaskHandler
{
    public function __construct(
        private readonly LockFactory $lock_factory
    )
    {

    }

    public function __invoke(
        ExpiredMessagesTaskMessage $expired_messages_task_message
    ):void
    {
        $lock = $this->lock_factory->createLock('expire_messages', 300);

        if (!$lock->acquire()){
            return;
        }

        try {
            error_log('expire_messages');

        } finally {
            $lock->release();
        }
    }
}