<?php declare(strict_types=1);

namespace App\ConsoleCommand;

use App\SchemaTask\SchemaTaskSchedule;
use App\Service\AssetsService;
use App\Service\MonitorProcessService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:release',
    description: 'Tasks for release: clearing caches, calculating hashes.'
)]
class ReleaseConsoleCommand extends Command
{
    public function __construct(
        protected MonitorProcessService $monitor_process_service,
        protected AssetsService $assets_service,
        protected SchemaTaskSchedule $schema_task_schedule
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        error_log('+------------------------+');
        error_log('| RELEASE                |');
        error_log('+------------------------+');
        error_log('');

        $clear_redis_cache_command = $this->getApplication()->find('app:clear-redis-cache');
        $clear_redis_cache_input = new ArrayInput([]);
        $clear_redis_cache_command->run($clear_redis_cache_input, $output);

        error_log('+------------------------+');
        error_log('| Redis cache cleared    |');
        error_log('+------------------------+');

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

        return 0;
    }
}
