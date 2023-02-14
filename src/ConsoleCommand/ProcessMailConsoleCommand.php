<?php declare(strict_types=1);

namespace App\ConsoleCommand;

use App\Queue\MailQueue;
use App\Service\MonitorProcessService;
use App\Service\QueueService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'process:mail',
    description: 'Send emails from queue'
)]
class ProcessMailConsoleCommand extends Command
{
    public function __construct(
        protected MonitorProcessService $monitor_process_service,
        protected MailQueue $mail_queue,
        protected QueueService $queue_service
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->monitor_process_service->boot('mail');

        while (true)
        {
            if (!$this->monitor_process_service->wait_most_recent())
            {
                continue;
            }

            $record = $this->queue_service->get(['mail']);

            if (count($record))
            {
                $this->mail_queue->process($record['data']);
            }

            $this->monitor_process_service->periodic_log();
        }

        return 0;
    }
}
