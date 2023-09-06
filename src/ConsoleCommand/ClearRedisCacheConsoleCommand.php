<?php declare(strict_types=1);

namespace App\ConsoleCommand;

use App\Service\ConfigService;
use App\Service\ResponseCacheService;
use App\Service\StaticContentService;
use App\Service\SystemsService;
use App\Service\UserCacheService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:clear-redis-cache',
    description: 'Clear Redis cache (no sessions). To be called on deploy.'
)]
class ClearRedisCacheConsoleCommand extends Command
{
    public function __construct(
        protected ConfigService $config_service,
        protected ResponseCacheService $response_cache_service,
        protected StaticContentService $static_content_service,
        protected UserCacheService $user_cache_service,
        protected SystemsService $systems_service
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schemas = $this->systems_service->get_schemas();

        foreach ($schemas as $schema)
        {
            $this->response_cache_service->clear_cache($schema);
            $this->config_service->clear_cache($schema);
            $this->static_content_service->clear_cache($schema);
            $this->user_cache_service->clear_all($schema);
        }

        return 0;
    }
}
