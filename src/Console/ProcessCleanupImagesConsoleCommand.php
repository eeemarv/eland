<?php declare(strict_types=1);

namespace App\Console;

use App\Service\MonitorProcessService;
use App\Task\CleanupImagesTask;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessCleanupImagesConsoleCommand extends Command
{
    protected static $defaultName = 'process:cleanup_images';

    protected MonitorProcessService $monitor_process_service;
    protected CleanupImagesTask $cleanup_images_task;

    public function __construct(
        MonitorProcessService $monitor_process_service,
        CleanupImagesTask $cleanup_images_task
    )
    {
        parent::__construct();

        $this->monitor_process_service = $monitor_process_service;
        $this->cleanup_images_task = $cleanup_images_task;
    }

    protected function configure()
    {
        $this->setDescription('Process to cleanup old image files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
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