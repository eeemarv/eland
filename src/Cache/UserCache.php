<?php declare(strict_types=1);

namespace App\Cache;

use App\Repository\UserRepository;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class UserCache
{
	const CACHE_TTL = 864000; // 10 days
	const CACHE_BETA = 1;

	public function __construct(
		protected UserRepository $user_repository,
		protected TagAwareCacheInterface $cache,
	)
	{
	}

	public function clear(int $id, string $schema):void
	{
		$this->cache->delete($this->get_key($id, $schema));
	}

	public function clear_all(string $schema):void
	{
		$this->cache->invalidateTags(['users.' . $schema]);
	}

	public function is_active_user(int $id, string $schema):bool
	{
		return $this->get($id, $schema)['is_active'];
	}

	public function get_role(int $id, string $schema):null|string
	{
		return $this->get($id, $schema)['role'];
	}

	private function get_key(int $id, string $schema):string
	{
		return 'users.' . $schema . '.' . $id;
	}

	public function get(int $id, string $schema):array
	{
		if (!$id)
		{
			return [];
		}

		$key = $this->get_key($id, $schema);

		return $this->cache->get($key, function(ItemInterface $item) use ($id, $schema){

			$item->tag(['users', 'users.' . $schema]);
			$item->expiresAfter(self::CACHE_TTL);

			return $this->user_repository->get_with_mollie_status($id, $schema);

		}, self::CACHE_BETA);
	}
}
