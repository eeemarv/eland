<?php declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessFetchElasIntersystemCommand extends Command
{
    protected static $defaultName = 'process:fetch_elas_intersystem';

    protected function configure()
    {
        $this->setDescription('Process to fetch data from eLAS interSystems.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $app = $this->getSilexApplication();

        $monitor_process_service->boot('fetch_elas_intersystem');

        while (true)
        {
            if (!$monitor_process_service->wait_most_recent())
            {
                continue;
            }

            $app['task.get_elas_intersystem_domains']->process();

            sleep(450);

            $app['task.fetch_elas_intersystem']->process();
            $monitor_process_service->periodic_log();
        }
    }
}
