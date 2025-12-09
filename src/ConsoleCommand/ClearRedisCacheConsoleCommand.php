<?php declare(strict_types=1);

namespace App\ConsoleCommand;

use App\Service\ConfigService;
use App\Service\StaticContentService;
use App\Service\SystemsService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
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
    private readonly TagAwareCacheInterface $cache,
    private readonly TypeaheadService $typeahead_service,
    private readonly StaticContentService $static_content_service,
    private readonly UserCacheService $user_cache_service,
    private readonly SystemsService $systems_service,
  )
  {
    parent::__construct();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $this->cache->invalidateTags([
      'assets',
      'response',
      'config',
      'static_content'
    ]);

    $schemas = $this->systems_service->get_schemas();

    foreach ($schemas as $schema)
    {
      $this->static_content_service->clear_cache($schema);
      $this->typeahead_service->clear_cache($schema);
      $this->user_cache_service->clear_all($schema);
    }

    return 0;
  }
}
