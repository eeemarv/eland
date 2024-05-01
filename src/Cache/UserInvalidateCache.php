<?php declare(strict_types=1);

namespace App\Cache;

use Symfony\Contracts\Cache\TagAwareCacheInterface;

class UserInvalidateCache
{
	public function __construct(
		protected TagAwareCacheInterface $cache,
	)
	{
	}

	public function user(int $id, string $schema):void
	{
		$this->cache->delete('users.' . $schema . '.' . $id);
	}

	public function schema(string $schema):void
	{
		$this->cache->invalidateTags(['users.' . $schema]);
	}

	public function all():void
	{
		$this->cache->invalidateTags(['users']);
	}
}
