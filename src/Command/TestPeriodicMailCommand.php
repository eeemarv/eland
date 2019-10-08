<?php declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class TestPeriodicMailCommand extends Command
{
    protected static $defaultName = 'test:periodic_mail';

    protected function configure()
    {
        $this->setDescription('Test sending a periodic mail');
        $this->setDefinition([
            new InputArgument('schema', InputArgument::REQUIRED, 'The schema'),
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $app = $this->getSilexApplication();

        error_log('TEST periodic mail');

        $schema = $input->getArgument('schema');

        $app['systems']->get_schemas();
        $app['schema_task.saldo']->set_schema($schema);
        $app['schema_task.saldo']->process();

        error_log('Ok.');
    }
}
