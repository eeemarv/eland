<?php declare(strict_types=1);

namespace App\ConsoleCommand;

use App\SchemaTask\UserExpMsgsSchemaTask;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class TestExpiredMessagesConsoleCommand extends Command
{
    protected static $defaultName = 'test:expired_messages';

    public function __construct(
        protected UserExpMsgsSchemaTask $user_exp_msgs_schema_task
    )
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Test notify expired messages');
        $this->setDefinition([
            new InputArgument('schema', InputArgument::REQUIRED, 'The schema'),
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schema = $input->getArgument('schema');

        $this->user_exp_msgs_schema_task->run($schema, false);

        error_log('Ok.');

        return 0;
    }
}
