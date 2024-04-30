<?php declare(strict_types=1);

namespace App\Service;

use App\Repository\SystemRepository;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class SystemsService
{
	const CACHE_KEY = 'systems';
	const CACHE_TTL = 86400; // one day
	const CACHE_BETA = 1;

	protected array $schema_ary;
	protected array $schemas;
	protected array $systems;

	public function __construct(
		protected Db $db,
		protected TagAwareCacheInterface $cache,
		protected SystemRepository $system_repository,
		#[Autowire('%env(LEGACY_ELAND_ORIGIN_PATTERN)%')]
		protected string $env_legacy_eland_origin_pattern
	)
	{
	}

	private function load():void
	{
		$this->schema_ary = $this->cache->get(self::CACHE_KEY, function(ItemInterface $item){

			$item->expiresAfter(self::CACHE_TTL);
			$item->tag(['deploy', 'systems']);

			return $this->system_repository->get_schema_ary();
		}, self::CACHE_BETA);
	}

	public function get_legacy_eland_origin(string $schema):string
	{
		if (!isset($this->systems[$schema]))
		{
			return '';
		}

		return str_replace('_', $this->systems[$schema], $this->env_legacy_eland_origin_pattern);
	}

	public function get_schema_from_legacy_eland_origin(string $origin):string
	{
		$host = strtolower(parse_url($origin, PHP_URL_HOST) ?? '');

		if (!$host)
		{
			return '';
		}

		[$system] = explode('.', $host);

		return $this->schemas[$system] ?? '';
 	}

	public function get_schema_ary():array
	{
		if (!isset($this->schema_ary))
		{
			$this->load();
		}

		return $this->schema_ary;
	}

	public function get_schema(string $system):null|string
	{
		if (!isset($this->schema_ary))
		{
			$this->load();
		}

		/** */
		$schema = $system;

		if (isset($this->schema_ary[$schema]))
		{
			return $schema;
		}

		return null;
	}

	public function get_system(string $schema):null|string
	{
		if (!isset($this->schema_ary))
		{
			$this->load();
		}

		if (isset($this->schema_ary[$schema]))
		{
			/** */
			$system = $schema;

			return $system;
		}

		return null;
	}

	public function count():int
	{
		if (!isset($this->schema_ary))
		{
			$this->load();
		}

		return count($this->schema_ary);
	}
}
