<?php

namespace eland;

use Doctrine\DBAL\Connection as db;
use Predis\Client as redis;

class interlets_groups
{
	public $ttl = 14400; // 4 hours
	private $redis;
	private $db;
	private $schemas;
	private $hosts;
	private $app_protocol;

	private $eland_ary;
	private $elas_ary;

	private $eland_accounts_schemas;
	private $ttl_eland_accounts_schemas = 86400; // 1 day

	public function __construct(db $db, redis $redis, array $schemas, array $hosts, string $app_protocol)
	{
		$this->db = $db;
		$this->redis = $redis;
		$this->schemas = $schemas;
		$this->hosts = $hosts;
		$this->app_protocol = $app_protocol;
	}

	/*
	 *
	 */

	function get_eland_accounts_schemas($schema)
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

	function clear_cache(string $s_schema)
	{
		$this->redis->del($s_schema . '_elas_interlets_groups');

		foreach ($this->schemas as $s)
		{
			$this->redis->del($s . '_eland_interlets_groups');
		}
	}

	/**
	 *
	 */

	function get_eland(string $s_schema, bool $refresh = false)
	{
		if (!$s_schema)
		{
			return [];
		}

		$redis_key = $s_schema . '_eland_interlets_groups';

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
			$h = get_host($row['url']);

			if (isset($this->schemas[$h]))
			{
				$interlets_hosts[] = $h;

				$this->eland_accounts_schemas[$row['id']] = $this->schemas[$h];
			}
		}

		// cache interlets account ids for user interlets linking. (in transactions)
		$key_interlets_accounts = $s_schema . '_interlets_accounts_schemas';

		$this->redis->set($key_interlets_accounts, json_encode($this->eland_accounts_schemas));

		$this->redis->expire($key_interlets_accounts, $this->ttl_eland_accounts_schemas);

		$s_url = $this->app_protocol . $this->hosts[$s_schema];

		$this->eland_ary = [];

		foreach ($interlets_hosts as $h)
		{
			$s = $this->schemas[$h];

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

	function get_elas(string $s_schema)
	{
		if (!$s_schema)
		{
			return [];
		}

		$redis_key = $s_schema . '_elas_interlets_groups';

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
			$h = get_host($row['url']);

			if (!(isset($this->schemas[$h])))
			{
				$this->elas_ary[$row['id']] = $row;
			}
		}

		$this->redis->set($redis_key, json_encode($this->elas_ary));
		$this->redis->expire($redis_key, $this->ttl);

		return $this->elas_ary;
	}
}
