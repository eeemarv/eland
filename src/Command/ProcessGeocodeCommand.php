<?php declare(strict_types=1);

namespace App\Command;

use App\Queue\GeocodeQueue;
use App\Service\MonitorProcessService;
use App\Service\QueueService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessGeocodeCommand extends Command
{
    protected static $defaultName = 'process:geocode';

    protected $monitor_process_service;
    protected $geocode_queue;
    protected $queue_service;

    public function __construct(
        MonitorProcessService $monitor_process_service,
        GeocodeQueue $geocode_queue,
        QueueService $queue_service
    )
    {
        parent::__construct();

        $this->monitor_process_service = $monitor_process_service;
        $this->geocode_queue = $geocode_queue;
        $this->queue_service = $queue_service;
    }

    protected function configure()
    {
        $this->setDescription('Process to retrieve geographic coordinates from geocoding API');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->monitor_process_service->boot('geocode');

        while (true)
        {
            if (!$this->monitor_process_service->wait_most_recent())
            {
                continue;
            }

            $record = $this->queue_service->get(['geocode']);

            if (count($record))
            {
                $this->geocode_queue->process($record['data']);
            }

            $this->monitor_process_service->periodic_log();
        }

        return 0;
    }
}
