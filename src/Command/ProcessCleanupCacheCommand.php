<?php declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessCleanupCacheCommand extends Command
{
    protected static $defaultName = 'process:cleanup_cache';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Process to cleanup the db cache periodically');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $app = $this->getSilexApplication();

        $app['monitor_process']->boot('cleanup_cache');

        while (true)
        {
            if (!$app['monitor_process']->wait_most_recent())
            {
                continue;
            }

            $app['cache']->cleanup();
            $app['monitor_process']->periodic_log();
        }
    }
}
