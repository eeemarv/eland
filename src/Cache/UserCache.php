<?php declare(strict_types=1);

namespace App\Cache;

use App\Repository\UserRepository;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class UserCache
{
	const CACHE_TTL = 86400; // 1 day
	const CACHE_BETA = 1;

	protected array $local = [];
	protected bool $local_en;

	public function __construct(
		protected UserRepository $user_repository,
		protected TagAwareCacheInterface $cache,
	)
	{
		$this->local_en = php_sapi_name() !== 'cli';
	}

	public function is_active_user(int $id, string $schema):bool
	{
		return $this->get($id, $schema)['is_active'];
	}

	public function get_role(int $id, string $schema):null|string
	{
		return $this->get($id, $schema)['role'];
	}

	public function get(int $id, string $schema):array
	{
		if (!$id)
		{
			return [];
		}

		if ($this->local_en || !isset($this->local[$schema][$id]))
		{
			$this->local[$schema][$id] = $this->cache->get('users.' . $schema . '.' . $id, function(ItemInterface $item) use ($id, $schema){
				$item->tag(['users', 'users.' . $schema]);
				$item->expiresAfter(self::CACHE_TTL);
				return $this->user_repository->get_with_mollie_status($id, $schema);
			}, self::CACHE_BETA);
		}

		return $this->local[$schema][$id];
	}
}
