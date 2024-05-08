<?php declare(strict_types=1);

namespace App\Cache;

use App\Repository\SystemRepository;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class SystemsCache
{
	const CACHE_KEY = 'systems';
	const CACHE_TTL = 86400;
	const CACHE_BETA = 1;

	protected array $schema_ary;
	protected bool $local_en;

	public function __construct(
		protected TagAwareCacheInterface $cache,
		protected SystemRepository $system_repository
	)
	{
		$this->local_en = php_sapi_name() !== 'cli';
	}

	public function get_schema_ary():array
	{
		if (!$this->local_en || !isset($this->schema_ary))
		{
			$this->schema_ary = $this->cache->get(self::CACHE_KEY, function(ItemInterface $item){
				$item->expiresAfter(self::CACHE_TTL);
				return $this->system_repository->get_schema_ary();
			}, self::CACHE_BETA);
		}

		return $this->schema_ary;
	}

	public function get_schema(string $system):null|string
	{
		$schema = $system;

		if (isset($this->get_schema_ary()[$schema]))
		{
			return $schema;
		}

		return null;
	}

	/**
	 * Returns an array with valid intersystem connections
	 * on the same server. The inter system connections
	 * must configured active in both systems in order
	 * to be valid.
	 */
	public function get_inter_schema_ary(string $schema):array
	{
		$schema_ary = $this->get_schema_ary();

		if (!isset($schema_ary[$schema]))
		{
			return [];
		}

		$inter_schema_ary = [];
		$ary = $schema_ary[$schema];

		foreach ($ary as $remote_schema => $remote_ary)
		{
			if (!isset($schema_ary[$remote_schema]))
			{
				continue;
			}

			if (!isset($schema_ary[$remote_schema][$schema]))
			{
				continue;
			}

			if (!$schema_ary[$remote_schema][$schema])
			{
				continue;
			}

			$inter_schema_ary[$remote_schema] = $remote_schema;
		}

		ksort($inter_schema_ary);

		return $inter_schema_ary;
	}

	public function get_system(string $schema):null|string
	{
		if (isset($this->get_schema_ary()[$schema]))
		{
			/** */
			$system = $schema;

			return $system;
		}

		return null;
	}

	public function count():int
	{
		return count($this->get_schema_ary());
	}
}
