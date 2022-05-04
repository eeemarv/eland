<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection as Db;
use Redis;
use App\Service\SystemsService;
use App\Service\ConfigService;

class IntersystemsService
{
	const ELAND = '_eland_intersystems';
	const ELAND_ACCOUNTS_SCHEMAS = '_eland_accounts_schemas';
	const TTL = 854000;
	const TTL_ELAND_ACCOUNTS_SCHEMAS = 864000;

	protected array $eland_ary = [];
	protected array $eland_accounts_schemas = [];
	protected array $eland_intersystems = [];

	public function __construct(
		protected Db $db,
		protected Redis $predis,
		protected SystemsService $systems_service,
		protected ConfigService $config_service
	)
	{
	}

	public function clear_cache():void
	{
		$this->clear_eland_cache();
	}

	public function clear_eland_cache():void
	{
		unset($this->eland_ary);
		unset($this->eland_account_schemas);

		foreach ($this->systems_service->get_schemas() as $schema)
		{
			$this->predis->del($schema . self::ELAND);
			$this->predis->del($schema . self::ELAND_ACCOUNTS_SCHEMAS);
		}
	}

	private function load_eland_intersystems_from_db(string $schema):void
	{
		$this->eland_intersystems[$schema] = [];
		$this->eland_accounts_schemas[$schema] = [];

		$stmt = $this->db->prepare('select g.url, u.id
			from ' . $schema . '.letsgroups g, ' . $schema . '.users u
			where g.apimethod = \'elassoap\'
				and u.code = g.localletscode
				and u.code <> \'\'
				and u.role = \'guest\'
				and u.status in (1, 2, 7)');

		$res = $stmt->executeQuery();

		while($row = $res->fetchAssociative())
		{
			$host = parse_url($row['url'], PHP_URL_HOST) ?? '';
			[$system] = explode('.', $host);
			$system = strtolower($system);

			if ($interschema = $this->systems_service->get_schema($system))
			{
				if (!$this->config_service->get_intersystem_en($interschema))
				{
					continue;
				}

				$this->eland_intersystems[$schema][] = $system;
				$this->eland_accounts_schemas[$schema][$row['id']] = $interschema;
			}
		}
	}

	public function get_eland_accounts_schemas(string $schema):array
	{
		if (!$schema)
		{
			return [];
		}

		if (isset($this->eland_accounts_schemas[$schema]))
		{
			return $this->eland_accounts_schemas[$schema];
		}

		$redis_key = $schema . self::ELAND_ACCOUNTS_SCHEMAS;

		if ($this->predis->exists($redis_key))
		{
			$this->predis->expire($redis_key, self::TTL_ELAND_ACCOUNTS_SCHEMAS);

			return $this->eland_accounts_schemas[$schema] = json_decode($this->predis->get($redis_key), true);
		}

		$this->load_eland_intersystems_from_db($schema);

		$this->predis->set($redis_key, json_encode($this->eland_accounts_schemas[$schema]));
		$this->predis->expire($redis_key, self::TTL_ELAND_ACCOUNTS_SCHEMAS);

		return $this->eland_accounts_schemas[$schema];
	}

	public function get_eland(string $s_schema):array
	{
		if (!$s_schema)
		{
			return [];
		}

		if (isset($this->eland_ary[$s_schema]))
		{
			return $this->eland_ary[$s_schema];
		}

		$redis_key = $s_schema . self::ELAND;

		if ($this->predis->exists($redis_key))
		{
			$this->predis->expire($redis_key, self::TTL);

			return $this->eland_ary[$s_schema] = json_decode($this->predis->get($redis_key), true);
		}

		if (!isset($this->eland_intersystems[$s_schema]))
		{
			$this->load_eland_intersystems_from_db($s_schema);
		}

		$s_url = $this->systems_service->get_legacy_eland_origin($s_schema);

		$this->eland_ary[$s_schema] = [];

		foreach ($this->eland_intersystems[$s_schema] as $intersystem)
		{
			$interschema = $this->systems_service->get_schema($intersystem);

			$url = $this->db->fetchOne('select g.url
				from ' . $interschema . '.letsgroups g, ' .
					$interschema . '.users u
				where g.apimethod = \'elassoap\'
					and u.code = g.localletscode
					and u.code <> \'\'
					and u.status in (1, 2, 7)
					and u.role = \'guest\'
					and g.url = ?',
				[$s_url], [\PDO::PARAM_STR]);

			if (!$url)
			{
				continue;
			}

			$this->eland_ary[$s_schema][$interschema] = $intersystem;
		}

		$this->predis->set($redis_key, json_encode($this->eland_ary[$s_schema]));
		$this->predis->expire($redis_key, self::TTL);

		return $this->eland_ary[$s_schema];
	}

	public function get_count(string $s_schema):int
	{
		return $this->get_eland_count($s_schema);
	}

	public function get_eland_count(string $s_schema):int
	{
		return count($this->get_eland($s_schema));
	}
}
