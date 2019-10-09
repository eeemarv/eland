<?php declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessCleanupLogsCommand extends Command
{
    protected static $defaultName = 'process:cleanup_logs';

    protected function configure()
    {
        $this->setDescription('Process to cleanup old log entries from db.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $app = $this->getSilexApplication();

        $monitor_process_service->boot('cleanup_logs');

        while (true)
        {
            if (!$monitor_process_service->wait_most_recent())
            {
                continue;
            }

            // $schema is not used, logs from all schemas are cleaned up.

            $treshold = gmdate('Y-m-d H:i:s', time() - 86400 * 120);

            $db->executeQuery('delete from xdb.logs
                where ts < ?', [$treshold]);

            $monitor_process_service->periodic_log();
        }
    }
}
