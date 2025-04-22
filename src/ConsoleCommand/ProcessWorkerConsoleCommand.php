<?php declare(strict_types=1);

namespace App\ConsoleCommand;

use App\SchemaTask\SchemaTaskSchedule;
use App\Service\MonitorProcessService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'process:worker',
    description: 'Several slow background processes and set asset hashes.'
)]
class ProcessWorkerConsoleCommand extends Command
{
    public function __construct(
        protected MonitorProcessService $monitor_process_service,
        protected SchemaTaskSchedule $schema_task_schedule
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        error_log('+------------------------+');
        error_log('| WORKER                 |');
        error_log('+------------------------+');

        $this->monitor_process_service->boot('worker');

        while (true)
        {
            if (!$this->monitor_process_service->wait_most_recent())
            {
                continue;
            }

            $this->schema_task_schedule->process();

            $this->monitor_process_service->periodic_log();
        }

        return 0;
    }
}
