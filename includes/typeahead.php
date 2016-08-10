<?php

namespace eland;

use Predis\Client as Redis;

class typeahead
{
	protected $redis;
	protected $base_url;
	protected $rootpath;
	protected $version;
	protected $ttl = 5184000; // 60 days

	public function __construct(Redis $redis, string $base_url, string $rootpath)
	{
		$this->redis = $redis;
		$this->base_url = $base_url;
		$this->rootpath = $rootpath;
		$this->version = getenv('TYPEAHEAD_VERSION') ?: '';
	}

	/*
	*
	*/

	public function get($name_ary, $group_url = false, $group_id = false)
	{
		$out = '';

		if (!is_array($name_ary))
		{
			$name_ary = [$name_ary];
		}

		foreach($name_ary as $name)
		{
			$out .= $this->get_thumbprint($name, $group_url) . '|';

			if (strpos($name, 'users_') !== false)
			{
				$status = str_replace('users_', '', $name);
				$out .= $this->rootpath . 'ajax/typeahead_users.php?status=' . $status;
				$out .= ($group_id) ? '&group_id=' . $group_id : '';
				$out .= '&' . http_build_query(get_session_query_param());
			}
			else
			{
				$out .= $this->rootpath . 'ajax/typeahead_' . $name . '.php?';
				$out .= http_build_query(get_session_query_param());
			}

			$out .= '|';
		}

		return rtrim($out, '|');
	}

	/**
	*
	*/

	private function get_thumbprint(string $name = 'users_active', $group_url = false)
	{
		$group_url = ($group_url) ?: $this->base_url;

		$key = $group_url . '_typeahead_thumbprint_' . $name;

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

	public function invalidate_thumbprint(string $name = 'users_active', $group_url = false, $new_thumbprint = false)
	{
		$group_url = ($group_url) ?: $this->base_url;

		$key = $group_url . '_typeahead_thumbprint_' . $name;

		if ($new_thumbprint)
		{
			if ($new_thumbprint != $this->redis->get($key))
			{
				$this->redis->set($key, $new_thumbprint);

				log_event('typeahead', 'new typeahead thumbprint ' . $new_thumbprint . ' for ' . $group_url . ' : ' . $name);
			}

			$this->redis->expire($key, $this->ttl);
		}
		else
		{
			$this->redis->del($key);

			log_event('typeahead', 'typeahead thumbprint deleted for ' . $group_url . ' : ' . $name);
		}
	}
}
