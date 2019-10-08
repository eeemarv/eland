<?php declare(strict_types=1);

namespace App\Command;

use Knp\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class process_cleanup_logs extends Command
{
    protected static $defaultName = 'process:cleanup_logs';

    protected function configure()
    {
        $this->setDescription('Process to cleanup old log entries from db.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $app = $this->getSilexApplication();

        $app['monitor_process']->boot('cleanup_logs');

        while (true)
        {
            if (!$app['monitor_process']->wait_most_recent())
            {
                continue;
            }

            // $schema is not used, logs from all schemas are cleaned up.

            $treshold = gmdate('Y-m-d H:i:s', time() - 86400 * 120);

            $app['db']->executeQuery('delete from xdb.logs
                where ts < ?', [$treshold]);

            $app['monitor_process']->periodic_log();
        }
    }
}
