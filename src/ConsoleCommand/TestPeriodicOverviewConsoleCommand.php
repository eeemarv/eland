<?php declare(strict_types=1);

namespace App\ConsoleCommand;

use App\SchemaTask\PeriodicOverviewSchemaTask;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand(name: 'test:periodic_overview')]
class TestPeriodicOverviewConsoleCommand extends Command
{
    public function __construct(
        protected PeriodicOverviewSchemaTask $periodic_overview_schema_task
    )
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Test sending a periodic overview');
        $this->setDefinition([
            new InputArgument('schema', InputArgument::REQUIRED, 'The schema'),
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        error_log('TEST periodic overview');

        $schema = $input->getArgument('schema');

        $this->periodic_overview_schema_task->run($schema, false);

        error_log('Ok.');

        return 0;
    }
}
