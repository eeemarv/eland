<?php declare(strict_types=1);

namespace command;

use Knp\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class process_log extends Command
{
    protected static $defaultName = 'process:log';

    protected function configure()
    {
        $this->setDescription('Process to pipe logs from Redis to db.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $app = $this->getSilexApplication();

        $app['monitor_process']->boot('log');

        while (true)
        {
            if (!$app['monitor_process']->wait_most_recent())
            {
                continue;
            }

            $app['log_db']->update();
            $app['monitor_process']->periodic_log();
        }
    }
}
