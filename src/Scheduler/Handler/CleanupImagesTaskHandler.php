<?php declare(strict_types=1);

namespace App\Scheduler\Handler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\Scheduler\Message\CleanupImagesTaskMessage;
use Symfony\Component\Lock\LockFactory;

#[AsMessageHandler]
final class CleanupImagesTaskHandler
{
    public function __construct(
        private readonly LockFactory $lock_factory
    )
    {
    }

    public function __invoke(
        CleanupImagesTaskMessage $cleanup_images_task_message
    ):void
    {
        $lock = $this->lock_factory->createLock('cleanup_images', 300);

        if (!$lock->acquire()){
            return;
        }

        try {
            error_log('cleanup_images');

        } finally {
            $lock->release();
        }
    }
}