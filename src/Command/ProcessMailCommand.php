<?php declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessMailCommand extends Command
{
    protected static $defaultName = 'process:mail';

    protected function configure()
    {
        $this->setDescription('Send emails from queue');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->monitor_process_service->boot('mail');

        while (true)
        {
            if (!$this->monitor_process_service->wait_most_recent())
            {
                continue;
            }

            $record = $app['queue']->get(['mail']);

            if (count($record))
            {
                $mail_queue->process($record['data']);
            }

            $this->monitor_process_service->periodic_log();
        }
    }
}
