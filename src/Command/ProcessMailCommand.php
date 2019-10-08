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

        $app = $this->getSilexApplication();

//        error_log($app->url('contact', ['system' => 'x']));

        $app['monitor_process']->boot('mail');

        while (true)
        {
            if (!$app['monitor_process']->wait_most_recent())
            {
                continue;
            }

            $record = $app['queue']->get(['mail']);

            if (count($record))
            {
                $app['queue.mail']->process($record['data']);
            }

            $app['monitor_process']->periodic_log();
        }
    }
}
