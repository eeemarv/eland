<?php

namespace service;

use Doctrine\DBAL\Connection as db;
use Predis\Client as redis;
use service\systems;
use service\config;

class intersystems
{
	const ELAND = '_eland_intersystems';
	const ELAS = '_elas_intersystems';
	const ELAND_ACCOUNTS_SCHEMAS = '_eland_accounts_schemas';
	const TTL = 854000;
	const TTL_ELAND_ACCOUNTS_SCHEMAS = 864000;

	protected $redis;
	protected $db;
	protected $systems;
	protected $config;
	protected $legacy_eland_host_pattern;

	protected $elas_ary = [];
	protected $eland_ary = [];
	protected $eland_accounts_schemas = [];
	protected $eland_intersystems = [];

	public function __construct(
		db $db,
		redis $redis,
		systems $systems,
		config $config,
		string $legacy_eland_host_pattern
	)
	{
		$this->db = $db;
		$this->redis = $redis;
		$this->systems = $systems;
		$this->config = $config;
		$this->legacy_eland_host_pattern = $legacy_eland_host_pattern;
	}

	public function clear_cache(string $s_schema):void
	{
		$this->clear_elas_cache($s_schema);
		$this->clear_eland_cache();
	}

	public function clear_elas_cache(string $s_schema):void
	{
		unset($this->elas_ary[$s_schema]);
		$this->redis->del($s_schema . self::ELAS);
	}

	public function clear_eland_cache():void
	{
		unset($this->eland_ary);
		unset($this->eland_account_schemas);

		foreach ($this->systems->get_schemas() as $schema)
		{
			$this->redis->del($schema . self::ELAND);
			$this->redis->del($schema . self::ELAND_ACCOUNTS_SCHEMAS);
		}
	}

	private function load_eland_intersystems_from_db(string $schema):void
	{
		$this->eland_intersystems[$schema] = [];
		$this->eland_accounts_schemas[$schema] = [];

		$st = $this->db->prepare('select g.url, u.id
			from ' . $schema . '.letsgroups g, ' . $schema . '.users u
			where g.apimethod = \'elassoap\'
				and u.letscode = g.localletscode
				and u.letscode <> \'\'
				and u.accountrole = \'interlets\'
				and u.status in (1, 2, 7)');

		$st->execute();

		while($row = $st->fetch())
		{
			$host = parse_url($row['url'], PHP_URL_HOST);
			[$system] = explode('.', $host);
			$system = strtolower($system);

			if ($interschema = $this->systems->get_schema_from_system($system))
			{
				if (!$this->config->get('template_lets', $interschema))
				{
					continue;
				}

				if (!$this->config->get('interlets_en', $interschema))
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

		if ($this->redis->exists($redis_key))
		{
			$this->redis->expire($redis_key, self::TTL_ELAND_ACCOUNTS_SCHEMAS);

			return $this->eland_accounts_schemas[$schema] = json_decode($this->redis->get($redis_key), true);
		}

		$this->load_eland_intersystems_from_db($schema);

		$this->redis->set($redis_key, json_encode($this->eland_accounts_schemas[$schema]));
		$this->redis->expire($redis_key, self::TTL_ELAND_ACCOUNTS_SCHEMAS);

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

		if ($this->redis->exists($redis_key))
		{
			$this->redis->expire($redis_key, self::TTL);

			return $this->eland_ary[$s_schema] = json_decode($this->redis->get($redis_key), true);
		}

		if (!isset($this->eland_intersystems[$s_schema]))
		{
			$this->load_eland_intersystems_from_db($s_schema);
		}

		$s_system = $this->systems->get_system_from_schema($s_schema);
		$s_url = str_replace('_', $s_system, $this->legacy_eland_host_pattern);
		$this->eland_ary[$s_schema] = [];

		foreach ($this->eland_intersystems[$s_schema] as $intersystem)
		{
			$interschema = $this->systems->get_schema_from_system($intersystem);

			$url = $this->db->fetchColumn('select g.url
				from ' . $interschema . '.letsgroups g, ' .
					$interschema . '.users u
				where g.apimethod = \'elassoap\'
					and u.letscode = g.localletscode
					and u.letscode <> \'\'
					and u.status in (1, 2, 7)
					and u.accountrole = \'interlets\'
					and g.url = ?', [$s_url]);

			if (!$url)
			{
				continue;
			}

			$this->eland_ary[$s_schema][$interschema] = $intersystem;
		}

		$this->redis->set($redis_key, json_encode($this->eland_ary[$s_schema]));
		$this->redis->expire($redis_key, self::TTL);

		return $this->eland_ary[$s_schema];
	}

	public function get_elas(string $s_schema):array
	{
		if (!$s_schema)
		{
			return [];
		}

		if (isset($this->elas_ary[$s_schema]))
		{
			return $this->elas_ary[$s_schema];
		}

		$redis_key = $s_schema . self::ELAS;

		if ($this->redis->exists($redis_key))
		{
			$this->redis->expire($redis_key, self::TTL);
			return $this->elas_ary[$s_schema] = json_decode($this->redis->get($redis_key), true);
		}

		$this->elas_ary[$s_schema] = [];

		$st = $this->db->prepare('select g.id, g.groupname, g.url
			from ' . $s_schema . '.letsgroups g, ' . $s_schema . '.users u
			where g.apimethod = \'elassoap\'
				and u.letscode = g.localletscode
				and g.groupname <> \'\'
				and g.url <> \'\'
				and g.myremoteletscode <> \'\'
				and g.remoteapikey <> \'\'
				and g.presharedkey <> \'\'
				and u.letscode <> \'\'
				and u.name <> \'\'
				and u.accountrole = \'interlets\'
				and u.status in (1, 2, 7)');

		$st->execute();

		while($row = $st->fetch())
		{
			$host = strtolower(parse_url($row['url'], PHP_URL_HOST));
			[$system] = explode('.', $host);

			if (!$this->systems->get_schema_from_system($system))
			{
				$row['domain'] = $host;
				$this->elas_ary[$s_schema][$row['id']] = $row;
			}
		}

		$this->redis->set($redis_key, json_encode($this->elas_ary[$s_schema]));
		$this->redis->expire($redis_key, self::TTL);

		return $this->elas_ary[$s_schema];
	}

	public function get_count(string $s_schema):int
	{
		return $this->get_eland_count($s_schema) + $this->get_elas_count($s_schema);
	}

	public function get_eland_count(string $s_schema):int
	{
		return count($this->get_eland($s_schema));
	}

	public function get_elas_count(string $s_schema):int
	{
		return count($this->get_elas($s_schema));
	}
}
