<?php declare(strict_types=1);

namespace App\Command;

use App\Service\MonitorProcessService;
use App\Task\FetchElasIntersystemTask;
use App\Task\GetElasIntersystemDomainsTask;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessFetchElasIntersystemCommand extends Command
{
    protected static $defaultName = 'process:fetch_elas_intersystem';

    protected $monitor_process_service;
    protected $get_elas_intersystem_domains_task;
    protected $fetch_elas_intersystem_task;

    public function __construct(
        MonitorProcessService $monitor_process_service,
        GetElasIntersystemDomainsTask $get_elas_intersystem_domains_task,
        FetchElasIntersystemTask $fetch_elas_intersystem_task
    )
    {
        parent::__construct();

        $this->monitor_process_service = $monitor_process_service;
        $this->get_elas_intersystem_domains_task = $get_elas_intersystem_domains_task;
        $this->fetch_elas_intersystem_task = $fetch_elas_intersystem_task;
    }

    protected function configure()
    {
        $this->setDescription('Process to fetch data from eLAS interSystems.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->monitor_process_service->boot('fetch_elas_intersystem');

        while (true)
        {
            if (!$this->monitor_process_service->wait_most_recent())
            {
                continue;
            }

            $this->get_elas_intersystem_domains_task->process();

            sleep(450);

            $this->fetch_elas_intersystem_task->process();
            $this->monitor_process_service->periodic_log();
        }
    }
}
