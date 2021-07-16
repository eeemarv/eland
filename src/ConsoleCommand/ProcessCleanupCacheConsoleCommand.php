<?php declare(strict_types=1);

namespace App\Command;

use App\Service\CacheService;
use App\Service\MonitorProcessService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessCleanupCacheCommand extends Command
{
    protected static $defaultName = 'process:cleanup_cache';

    public function __construct(
        protected MonitorProcessService $monitor_process_service,
        protected CacheService $cache_service
    )
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Process to cleanup the db cache periodically');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->monitor_process_service->boot('cleanup_cache');

        while (true)
        {
            if (!$this->monitor_process_service->wait_most_recent())
            {
                continue;
            }

            $this->cache_service->cleanup();
            $this->monitor_process_service->periodic_log();
        }

        return 0;
    }
}
