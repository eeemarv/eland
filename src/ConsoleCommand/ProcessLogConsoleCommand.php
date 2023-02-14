<?php declare(strict_types=1);

namespace App\ConsoleCommand;

use App\Service\LogDbService;
use App\Service\MonitorProcessService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'process:log',
    description: 'Process to pipe logs from Redis to db.'
)]
class ProcessLogConsoleCommand extends Command
{
    public function __construct(
        protected MonitorProcessService $monitor_process_service,
        protected LogDbService $log_db_service
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->monitor_process_service->boot('log');

        while (true)
        {
            if (!$this->monitor_process_service->wait_most_recent())
            {
                continue;
            }

            $this->log_db_service->update();
            $this->monitor_process_service->periodic_log();
        }

        return 0;
    }
}
