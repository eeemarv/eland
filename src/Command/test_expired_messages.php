<?php declare(strict_types=1);

namespace command;

use Knp\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class test_expired_messages extends Command
{
    protected static $defaultName = 'test:expired_messages';

    protected function configure()
    {
        $this->setDescription('Test notify expired messages');
        $this->setDefinition([
            new InputArgument('schema', InputArgument::REQUIRED, 'The schema'),
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $app = $this->getSilexApplication();

        $schema = $input->getArgument('schema');

        $app['systems']->get_schemas();
        $app['schema_task.user_exp_msgs']->set_schema($schema);
        $app['schema_task.user_exp_msgs']->process(false);

        error_log('Ok.');
    }
}
