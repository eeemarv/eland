<?php declare(strict_types=1);

namespace command;

use Knp\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class process_fetch_elas_intersystem extends Command
{
    protected static $defaultName = 'process:fetch_elas_intersystem';

    protected function configure()
    {
        $this->setDescription('Process to fetch data from eLAS interSystems.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $app = $this->getSilexApplication();

        $app['monitor_process']->boot('fetch_elas_intersystem');

        while (true)
        {
            if (!$app['monitor_process']->wait_most_recent())
            {
                continue;
            }

            $app['task.get_elas_intersystem_domains']->process();

            sleep(450);

            $app['task.fetch_elas_intersystem']->process();
            $app['monitor_process']->periodic_log();
        }
    }
}
