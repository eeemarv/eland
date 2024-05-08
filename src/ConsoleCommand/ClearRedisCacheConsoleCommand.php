<?php declare(strict_types=1);

namespace App\ConsoleCommand;

use App\Cache\SystemsInvalidateCache;
use App\Cache\UserInvalidateCache;
use App\Service\ConfigService;
use App\Service\ResponseCacheService;
use App\Service\StaticContentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

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
        protected TagAwareCacheInterface $cache,
        protected UserInvalidateCache $user_invalidate_cache,
        protected SystemsInvalidateCache $systems_invalidate_cache
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ([] as $schema)
        {
            $this->response_cache_service->clear_cache($schema);
            $this->config_service->clear_cache($schema);
            $this->static_content_service->clear_cache($schema);
        }

        $this->cache->invalidateTags([
            'response',
            'config',
            'static_content'
        ]);

        $this->user_invalidate_cache->all();
        $this->systems_invalidate_cache->all();

        return 0;
    }
}
