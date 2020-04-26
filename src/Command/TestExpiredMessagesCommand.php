<?php declare(strict_types=1);

namespace App\Command;

use App\SchemaTask\UserExpMsgsSchemaTask;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class TestExpiredMessagesCommand extends Command
{
    protected static $defaultName = 'test:expired_messages';

    protected $user_exp_msgs_schema_task;

    public function __construct(
        UserExpMsgsSchemaTask $user_exp_msgs_schema_task
    )
    {
        parent::__construct();
        $this->user_exp_msgs_schema_task = $user_exp_msgs_schema_task;
    }

    protected function configure()
    {
        $this->setDescription('Test notify expired messages');
        $this->setDefinition([
            new InputArgument('schema', InputArgument::REQUIRED, 'The schema'),
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $schema = $input->getArgument('schema');

        $this->user_exp_msgs_schema_task->run($schema, false);

        error_log('Ok.');

        return 0;
    }
}
