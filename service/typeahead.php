<?php

namespace eland;

use Predis\Client as Redis;
use Monolog\Logger;

class typeahead
{
	private $redis;
	private $monolog;
	private $version;
	private $ttl = 5184000; // 60 days

	public function __construct(Redis $redis, Logger $monolog)
	{
		$this->redis = $redis;
		$this->monolog = $monolog;
		$this->version = getenv('TYPEAHEAD_VERSION') ?: '';
	}

	/*
	*
	*/

	public function get($name_ary, $group_domain = false, $group_id = false)
	{
		$out = '';

		if (!is_array($name_ary))
		{
			$name_ary = [$name_ary];
		}

		foreach($name_ary as $name)
		{
			$out .= $this->get_thumbprint($name, $group_domain) . '|';

			if (strpos($name, 'users_') !== false)
			{
				$status = str_replace('users_', '', $name);
				$out .= './ajax/typeahead_users.php?status=' . $status;
				$out .= ($group_id) ? '&group_id=' . $group_id : '';
				$out .= '&' . http_build_query(get_session_query_param());
			}
			else
			{
				$out .= './ajax/typeahead_' . $name . '.php?';
				$out .= http_build_query(get_session_query_param());
			}

			$out .= '|';
		}

		return rtrim($out, '|');
	}

	/**
	*
	*/

	private function get_thumbprint(string $name = 'users_active', $group_domain = false)
	{
		$group_domain = ($group_domain) ?: $_SERVER['SERVER_NAME'];

		$key = $group_domain . '_typeahead_thumbprint_' . $name;

		$thumbprint = $this->redis->get($key);

		if (!$thumbprint)
		{
			return 'renew-' . crc32(microtime());
		}

		return $this->version . $thumbprint;
	}

	/**
	*
	*/

	public function invalidate_thumbprint(string $name = 'users_active', $group_domain = false, $new_thumbprint = false)
	{
		$group_domain = ($group_domain) ?: $_SERVER['SERVER_NAME'];

		$key = $group_domain . '_typeahead_thumbprint_' . $name;

		if ($new_thumbprint)
		{
			if ($new_thumbprint != $this->redis->get($key))
			{
				$this->redis->set($key, $new_thumbprint);

				$this->monolog->debug('typeahead: new typeahead thumbprint ' . $new_thumbprint . ' for ' . $group_domain . ' : ' . $name);
			}

			$this->redis->expire($key, $this->ttl);
		}
		else
		{
			$this->redis->del($key);

			$this->monolog->debug('typeahead: typeahead thumbprint deleted for ' . $group_domain . ' : ' . $name);
		}
	}
}
