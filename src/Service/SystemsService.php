<?php declare(strict_types=1);

namespace App\Service;

use App\DTO\Schema;
use App\Repository\SystemRepository;
use Deprecated;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class SystemsService
{
	const CACHE_KEY = 'systems';
	const CACHE_TTL = 86400;
	const CACHE_BETA = 1;

	private array $local_cache_ary;
	private bool $local_en;

	protected array $schemas = [];
	protected array $systems = [];

	public function __construct(
    private readonly SystemRepository $system_repository,
    private readonly TagAwareCacheInterface $cache,
    private readonly ConfigService $config_service,
		//protected Db $db,
		//#[Autowire('%env(LEGACY_ELAND_ORIGIN_PATTERN)%')]
		//protected string $env_legacy_eland_origin_pattern
	)
	{
		$this->local_en = php_sapi_name() !== 'cli';

    /*
		$stmt = $this->db->prepare('select schema_name
			from information_schema.schemata');

		$res = $stmt->executeQuery();

		while($row = $res->fetchAssociative())
		{
			$schema = $row['schema_name'];

			if (isset(self::IGNORE[$schema]))
			{
				continue;
			}

			if (str_starts_with($schema, 'pg_'))
			{
				continue;
			}

			$system = $schema;

			$this->schemas[$system] = $schema;
			$this->systems[$schema] = $system;
		}
    */
	}

  private function read_ary():array
	{
    $ary = $this->cache->get(self::CACHE_KEY, function(ItemInterface $item){
      $item->expiresAfter(self::CACHE_TTL);
      $item->tag('systems');
      return $this->system_repository->get_schema_ary();
    }, self::CACHE_BETA);

		if ($this->local_en)
		{
      $this->local_cache_ary = $ary;
    }

    error_log('=== systems_ary ===');
    error_log(json_encode($ary));

		return $ary;
	}

  /*
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
  */

  public function has_schema(string $schema):bool
	{
		if (!isset($this->local_cache_ary))
		{
      return isset($this->read_ary()[$schema]);
		}

		return isset($this->local_cache_ary[$schema]);
	}

  public function get_all():array
  {
    return $this->local_cache_ary ?? $this->read_ary();
  }

	/**
	 * Returns an array with valid intersystem connections
	 * on the same server. The inter system connections
	 * must configured active in both systems in order
	 * to be valid.
	 */
	public function get_inter_ary(
    string $schema
  ):array
	{
    /**
     * intersystem not enabled in config
     */
    if (!$this->config_service->get_bool('intersystem.enabled', new Schema($schema)))
    {
      return [];
    }

		$ary = $this->get_all();

		if (!isset($ary[$schema]))
		{
			return [];
		}

    $schema_ary = $ary[$schema];
		$inter_ary = [];

		foreach ($schema_ary as $remote_schema => $active)
		{
      /**
       * The intersystem account is not active
       */
      if (!$active)
      {
        continue;
      }

      /**
       * remote_schema does not exist
       */
			if (!isset($ary[$remote_schema]))
			{
				continue;
			}

      /**
       * No intersystem account exists in remote schema pointing back
       */
			if (!isset($ary[$remote_schema][$schema]))
			{
				continue;
			}

      /**
       * the remote intersystem account is not active.
       */
			if (!$ary[$remote_schema][$schema])
			{
				continue;
			}

      /**
       * intersystem not enabled in remote system
       */
      if (!$this->config_service->get_bool('intersystem.enabled', new Schema($remote_schema)))
      {
        continue;
      }

			$inter_ary[$remote_schema] = $remote_schema;
		}

		return $inter_ary;
	}

  #[Deprecated()]
	public function get_schemas():array
	{
    $ary = $this->get_all();
    $schema_ary = array_keys($ary);
		return array_combine($schema_ary, $schema_ary);
	}

  #[Deprecated()]
	public function get_systems():array
	{
		return $this->get_schemas();
	}

  #[Deprecated()]
	public function get_schema(string $system):string
	{
		return $this->has_schema($system) ? $system : '';
	}

  #[Deprecated()]
  public function get_schema_o(string $system):Schema
  {
    if (!$this->has_schema($system))
    {
      throw new \Exception('System ' . $system . ' not found.');
    }

    return new Schema($system);
  }

  #[Deprecated()]
	public function get_system(string $schema):string
	{
		return $this->has_schema($schema) ? $schema : '';
	}

  #[Deprecated()]
	public function count():int
	{
		return count($this->get_all());
	}
}
