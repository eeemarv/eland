<?php declare(strict_types=1);

namespace App\Command;

use App\Service\MonitorProcessService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessCleanupLogsCommand extends Command
{
    protected static $defaultName = 'process:cleanup_logs';

    protected $monitor_process_service;
    protected $db;

    public function __construct(
        MonitorProcessService $monitor_process_service,
        Db $db
    )
    {
        parent::__construct();

        $this->monitor_process_service = $monitor_process_service;
        $this->db = $db;
    }

    protected function configure()
    {
        $this->setDescription('Process to cleanup old log entries from db.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
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

            $this->db->executeQuery('delete from xdb.logs
                where ts < ?', [$treshold]);

            $this->monitor_process_service->periodic_log();
        }
    }
}
