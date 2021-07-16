<?php declare(strict_types=1);

namespace App\ConsoleCommand;

use App\Service\ConfigService;
use App\Service\StaticContentService;
use App\Service\SystemsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearRedisCacheConsoleCommand extends Command
{
    protected static $defaultName = 'app:clear-redis-cache';

    public function __construct(
        protected ConfigService $config_service,
        protected StaticContentService $static_content_service,
        protected SystemsService $systems_service
    )
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Clear Redis cache (no sessions). To be called on deploy.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $schemas = $this->systems_service->get_schemas();

        foreach ($schemas as $schema)
        {
            $this->config_service->clear_cache($schema);
            $this->static_content_service->clear_cache($schema);
        }

        return 0;
    }
}
