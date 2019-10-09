<?php declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessLogCommand extends Command
{
    protected static $defaultName = 'process:log';

    protected function configure()
    {
        $this->setDescription('Process to pipe logs from Redis to db.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $app = $this->getSilexApplication();

        $monitor_process_service->boot('log');

        while (true)
        {
            if (!$monitor_process_service->wait_most_recent())
            {
                continue;
            }

            $log_db_service->update();
            $monitor_process_service->periodic_log();
        }
    }
}
