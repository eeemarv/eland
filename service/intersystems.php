<?php

namespace service;

use Doctrine\DBAL\Connection as db;
use Predis\Client as redis;
use service\systems;
use service\config;

class intersystems
{
	protected $ttl = 14400; // 4 hours
	protected $redis;
	protected $db;
	protected $systems;
	protected $config;
	protected $app_protocol;

	protected $eland_ary;
	protected $elas_ary;

	protected $eland_accounts_schemas;
	protected $ttl_eland_accounts_schemas = 86400; // 1 day

	public function __construct(
		db $db,
		redis $redis,
		systems $systems,
		config $config,
		string $app_protocol
	)
	{
		$this->db = $db;
		$this->redis = $redis;
		$this->systems = $systems;
		$this->config = $config;
		$this->app_protocol = $app_protocol;
	}

	public function get_eland_accounts_schemas(string $schema):array
	{
		$ret = json_decode($this->redis->get($schema . '_interlets_accounts_schemas'), true);

		if (is_array($ret))
		{
			return $ret;
		}

		$this->get_eland($schema, true);

		return json_decode($this->redis->get($schema . '_interlets_accounts_schemas'), true);
	}

	/**
	*
	*/

	public function clear_cache(string $s_schema):void
	{
		$this->clear_elas_cache($s_schema);
		$this->clear_eland_cache();
	}

	/**
	 *
	 */

	public function clear_elas_cache(string $s_schema):void
	{
		$this->redis->del($s_schema . '_elas_interlets_groups');
		$this->redis->del($s_schema . '_elas_intersystems');
	}

	/**
	 *
	 */

	public function clear_eland_cache():void
	{
		foreach ($this->systems->get_schemas() as $s)
		{
			$this->redis->del($s . '_eland_interlets_groups');
			$this->redis->del($s . '_eland_intersystems');
		}
	}

	/**
	 *
	 */

	public function get_eland(string $s_schema, bool $refresh = false):array
	{
		if (!$s_schema)
		{
			return [];
		}

		$redis_key = $s_schema . '_eland_intersystems';

		if (!$refresh && $this->redis->exists($redis_key))
		{
			$this->redis->expire($redis_key, $this->ttl);

			return json_decode($this->redis->get($redis_key), true);
		}

		$interlets_hosts = $this->eland_accounts_schemas = [];

		$st = $this->db->prepare('select g.url, u.id
			from ' . $s_schema . '.letsgroups g, ' . $s_schema . '.users u
			where g.apimethod = \'elassoap\'
				and u.letscode = g.localletscode
				and u.letscode <> \'\'
				and u.accountrole = \'interlets\'
				and u.status in (1, 2, 7)');

		$st->execute();

		while($row = $st->fetch())
		{
			$h = strtolower(parse_url($row['url'], PHP_URL_HOST));

			if ($s = $this->systems->get_schema($h))
			{
				// ignore if the group is not LETS or not interLETS

				if (!$this->config->get('template_lets', $s))
				{
					continue;
				}

				if (!$this->config->get('interlets_en', $s))
				{
					continue;
				}

				$interlets_hosts[] = $h;

				$this->eland_accounts_schemas[$row['id']] = $s;
			}
		}

		// cache interlets account ids for user interlets linking. (in transactions)
		$key_interlets_accounts = $s_schema . '_interlets_accounts_schemas';

		$this->redis->set($key_interlets_accounts, json_encode($this->eland_accounts_schemas));

		$this->redis->expire($key_interlets_accounts, $this->ttl_eland_accounts_schemas);

		$s_url = $this->app_protocol . $this->systems->get_host($s_schema);

		$this->eland_ary = [];

		foreach ($interlets_hosts as $h)
		{
			$s = $this->systems->get_schema($h);

			$url = $this->db->fetchColumn('select g.url
				from ' . $s . '.letsgroups g, ' . $s . '.users u
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

			$this->eland_ary[$s] = $h;
		}

		$this->redis->set($redis_key, json_encode($this->eland_ary));
		$this->redis->expire($redis_key, $this->ttl);

		return $this->eland_ary;
	}

	/**
	 *
	 */

	public function get_elas(string $s_schema):array
	{
		if (!$s_schema)
		{
			return [];
		}

		$redis_key = $s_schema . '_elas_intersystems';

		if ($this->redis->exists($redis_key))
		{
			$this->redis->expire($redis_key, $this->ttl);
			return json_decode($this->redis->get($redis_key), true);
		}

		$this->elas_ary = [];

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
			$h = strtolower(parse_url($row['url'], PHP_URL_HOST));

			if (!$this->systems->get_schema($h))
			{
				$row['domain'] = $h;
				$this->elas_ary[$row['id']] = $row;
			}
		}

		$this->redis->set($redis_key, json_encode($this->elas_ary));
		$this->redis->expire($redis_key, $this->ttl);

		return $this->elas_ary;
	}
}
