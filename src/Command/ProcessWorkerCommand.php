<?php declare(strict_types=1);

namespace App\Command;

use App\SchemaTask\SchemaTaskSchedule;
use App\Service\AssetsService;
use App\Service\MonitorProcessService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessWorkerCommand extends Command
{
    protected static $defaultName = 'process:worker';

    protected $monitor_process_service;
    protected $assets_service;
    protected $schema_task_schedule;

    public function __construct(
        MonitorProcessService $monitor_process_service,
        AssetsService $assets_service,
        SchemaTaskSchedule $schema_task_schedule
    )
    {
        parent::__construct();

        $this->monitor_process_service = $monitor_process_service;
        $this->assets_service = $assets_service;
        $this->schema_task_schedule = $schema_task_schedule;
    }

    protected function configure()
    {
        $this->setDescription('Several slow background processes and set asset hashes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->monitor_process_service->boot('worker');

        $this->assets_service->write_file_hash_ary();

        error_log('+------------------------+');
        error_log('| Schema Tasks           |');
        error_log('+------------------------+');

        foreach ($this->schema_task_schedule->get_schema_task_names() as $name)
        {
            error_log((string) $name);
        }

        error_log('+------------------------+');
        error_log('| Last runs              |');
        error_log('+------------------------+');

        error_log(json_encode($this->schema_task_schedule->get_last_run_ary()));

        error_log('+------------------------+');

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
