<?php declare(strict_types=1);

namespace App\ConsoleCommand;

use App\Service\CacheService;
use App\Service\MonitorProcessService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'process:cleanup_cache',
    description: 'Process to cleanup the db cache periodically'
)]
class ProcessCleanupCacheConsoleCommand extends Command
{
    public function __construct(
        protected MonitorProcessService $monitor_process_service,
        protected CacheService $cache_service
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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
