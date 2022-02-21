<?php declare(strict_types=1);

namespace App\ConsoleCommand;

use Doctrine\DBAL\Connection as Db;
use App\Service\CacheService;
use App\Service\MonitorProcessService;
use App\Task\CleanupImagesTask;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessCleanupConsoleCommand extends Command
{
    protected static $defaultName = 'process:cleanup';

    public function __construct(
        protected MonitorProcessService $monitor_process_service,
        protected CleanupImagesTask $cleanup_images_task,
        protected CacheService $cache_service,
        protected Db $db
    )
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Process to cleanup images, cache and logs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->monitor_process_service->boot('cleanup');

        while (true)
        {
            if (!$this->monitor_process_service->wait_most_recent())
            {
                continue;
            }

            $select = $this->monitor_process_service->get_loop_count() % 16;

            switch ($select)
            {
                case 0: // cleanup logs
                    $treshold = gmdate('Y-m-d H:i:s', time() - 86400 * 120);

                    $this->db->executeStatement('delete from xdb.logs
                        where ts < ?', [$treshold]);

                    error_log('Cleanup logs.');
                break;

                case 1:
                    $this->cache_service->cleanup();
                    error_log('Cleanup cache.');
                break;

                default:
                    $this->cleanup_images_task->process();
                break;
            }

            $this->monitor_process_service->periodic_log();
        }

        return 0;
    }
}
