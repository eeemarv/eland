<?php

namespace command;

use Knp\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class process_geocode extends Command
{
    protected static $defaultName = 'process:geocode';

    protected function configure()
    {
        $this->setDescription('Process to retrieve geographic coordinates from geocoding API');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $app = $this->getSilexApplication();

        $app['monitor_process']->boot('geocode');

        while (true)
        {
            if (!$app['monitor_process']->wait_most_recent())
            {
                continue;
            }

            $record = $app['queue']->get(['geocode']);

            if (count($record))
            {
                $app['queue.geocode']->process($record['data']);
            }

            $app['monitor_process']->periodic_log();
        }
    }
}
