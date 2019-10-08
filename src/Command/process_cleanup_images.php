<?php declare(strict_types=1);

namespace command;

use Knp\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class process_cleanup_images extends Command
{
    protected static $defaultName = 'process:cleanup_images';

    protected function configure()
    {
        $this->setDescription('Process to cleanup old image files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $app = $this->getSilexApplication();

        $app['monitor_process']->boot('cleanup_images');

        while (true)
        {
            if (!$app['monitor_process']->wait_most_recent())
            {
                continue;
            }

            $app['task.cleanup_images']->process();
            $app['monitor_process']->periodic_log();
        }
    }
}
