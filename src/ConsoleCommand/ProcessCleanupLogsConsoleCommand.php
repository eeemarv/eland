<?php declare(strict_types=1);

namespace App\ConsoleCommand;

use App\Service\MonitorProcessService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'process:cleanup_logs',
    description: 'Process to cleanup old log entries from db.'
)]
class ProcessCleanupLogsConsoleCommand extends Command
{
    public function __construct(
        protected MonitorProcessService $monitor_process_service,
        protected Db $db
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->monitor_process_service->boot('cleanup_logs');

        while (true)
        {
            if (!$this->monitor_process_service->wait_most_recent())
            {
                continue;
            }

            // $chema is not used, logs from all schemas are cleaned up.

            $treshold = gmdate('Y-m-d H:i:s', time() - 86400 * 120);

            $this->db->executeStatement('delete from xdb.logs
                where ts < ?', [$treshold]);

            $this->monitor_process_service->periodic_log();
        }

        return 0;
    }
}
