<?php declare(strict_types=1);

namespace App\Command;

use App\SchemaTask\SaldoSchemaTask;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class TestPeriodicMailCommand extends Command
{
    protected static $defaultName = 'test:periodic_mail';

    protected $saldo_schema_task;

    protected function configure()
    {
        $this->setDescription('Test sending a periodic mail');
        $this->setDefinition([
            new InputArgument('schema', InputArgument::REQUIRED, 'The schema'),
        ]);
    }

    public function __construct(
        SaldoSchemaTask $saldo_schema_task
    )
    {
        parent::__construct();

        $this->saldo_schema_task = $saldo_schema_task;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        error_log('TEST periodic mail');

        $schema = $input->getArgument('schema');

        $this->saldo_schema_task->run($schema, false);

        error_log('Ok.');
    }
}
