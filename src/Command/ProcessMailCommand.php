<?php declare(strict_types=1);

namespace App\Command;

use App\Queue\MailQueue;
use App\Service\MonitorProcessService;
use App\Service\QueueService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessMailCommand extends Command
{
    protected static $defaultName = 'process:mail';

    protected $monitor_process_service;
    protected $mail_queue;
    protected $queue_service;

    public function __construct(
        MonitorProcessService $monitor_process_service,
        MailQueue $mail_queue,
        QueueService $queue_service
    )
    {
        parent::__construct();

        $this->monitor_process_service = $monitor_process_service;
        $this->mail_queue = $mail_queue;
        $this->queue_service = $queue_service;
    }

    protected function configure()
    {
        $this->setDescription('Send emails from queue');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
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