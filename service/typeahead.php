<?php

namespace service;

use Predis\Client as Redis;
use Monolog\Logger;

class typeahead
{
	protected $redis;
	protected $monolog;
	protected $ttl = 5184000; // 60 days

	public function __construct(Redis $redis, Logger $monolog)
	{
		$this->redis = $redis;
		$this->monolog = $monolog;
	}

	public function get(array $identifiers):string
	{
		$out = [];

		foreach($identifiers as $identifier)
		{
			$name = $identifier[0];
			$params = $identifier[1];

			$rec = [
				'thumbprint'	=> $this->get_thumbprint($name, $params),
				'name'			=> $name,
				'params'		=> $params,
			];

			$out[] = $rec;
		}

		return htmlspecialchars(json_encode($out));
	}

	protected function get_thumbprint_key(string $name, array $params):string
	{
		ksort($params);
		$key = 'typeahead_thumbprint_';
		$key .= $name;
		$key .= '_';
		$key .= http_build_query($params);
		return $key;
	}

	protected function get_thumbprint(string $name, array $params):string
	{
		$key = $this->get_thumbprint_key($name, $params);

		$thumbprint = $this->redis->get($key);

		if (!$thumbprint)
		{
			$thumbprint = 'renew-' . crc32(microtime());
			$this->monolog->debug('typeahead thumbprint ' .
				$thumbprint . ' for ' . $key,
				$this->get_log_params($params)
			);
		}

		return $thumbprint;
	}

	public function delete_thumbprint(
		string $name,
		string $params
	):void
	{
		$key = $this->get_thumbprint_key($name, $params);
		$this->redis->del($key);
		$this->monolog->debug('typeahead delete thumbprint for '
			. $key, $this->get_log_params($params));
	}

	protected function get_log_params(array $params):array
	{
		if (isset($params['schema']))
		{
			return ['schema' => $params['schema']];
		}

		return [];
	}

	public function set_thumbprint(
		string $name,
		array $params,
		string $new_thumbprint
	):void
	{
		$key = $this->get_thumbprint_key($name, $params);

		if ($new_thumbprint !== $this->redis->get($key))
		{
			$this->redis->set($key, $new_thumbprint);

			$this->monolog->debug('typeahead: new thumbprint ' .
				$new_thumbprint .
				' for ' . $key,
				$this->get_log_params($params)
			);
		}

		$this->redis->expire($key, $this->ttl);
	}
}
