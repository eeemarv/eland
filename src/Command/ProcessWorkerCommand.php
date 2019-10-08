<?php declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use util\task_container;

class ProcessWorkerCommand extends Command
{
    protected static $defaultName = 'process:worker';

    protected function configure()
    {
        $this->setDescription('Several slow background processes and set asset hashes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $app = $this->getSilexApplication();

        $app['monitor_process']->boot('worker');

        error_log(' --- ');
        error_log('schemas: ' . json_encode($app['systems']->get_schemas()));
        error_log(' --- ');

        $app['assets']->write_file_hash_ary();

        error_log('+-----------------+');
        error_log('| Worker Tasks    |');
        error_log('+-----------------+');

        $schema_task = new task_container($app, 'schema_task');

        while (true)
        {
            if (!$app['monitor_process']->wait_most_recent())
            {
                continue;
            }

            if ($schema_task->should_run())
            {
                $schema_task->run();
            }

            $app['monitor_process']->periodic_log();
        }
    }
}
