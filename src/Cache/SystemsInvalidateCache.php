<?php declare(strict_types=1);

namespace App\Cache;

use Symfony\Contracts\Cache\TagAwareCacheInterface;

class SystemsInvalidateCache
{
	const CACHE_KEY = 'systems';

	public function __construct(
		protected TagAwareCacheInterface $cache,
	)
	{
	}

	public function all():void
	{
		$this->cache->delete(self::CACHE_KEY);
	}
}
