<?php declare(strict_types=1);

namespace App\ConsoleCommand;

use App\Service\MonitorProcessService;
use App\Task\CleanupImagesTask;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'process:cleanup_images',
    description: 'Process to cleanup old image files.'
)]
class ProcessCleanupImagesConsoleCommand extends Command
{
    public function __construct(
        protected MonitorProcessService $monitor_process_service,
        protected CleanupImagesTask $cleanup_images_task
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->monitor_process_service->boot('cleanup_images');

        while (true)
        {
            if (!$this->monitor_process_service->wait_most_recent())
            {
                continue;
            }

            $this->cleanup_images_task->process();
            $this->monitor_process_service->periodic_log();
        }

        return 0;
    }
}
