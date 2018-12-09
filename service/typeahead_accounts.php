<?php

namespace service;

use service\typeahead;
use service\cache;

class typeahead_accounts
{
	protected $typeahead;
	protected $cache;

	public function __construct(typeahead $typeahead, cache $cache)
	{
		$this->typeahead = $typeahead;
		$this->cache = $cache;
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

	public function delete_thumbprint__(
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

	public function delete_thumbprint(string $schema, string $status):void
	{
		$params = [
			'schema'	=> $schema,
			'status'	=> $status,
		];

		$this->typeahead->delete_thumbprint('accounts', $params);

		if ($status === 'active')
		{

		}
		$params = [
			'schema'	=> ''
		];

	}

	public function set_thumbprint_elas_domain(
		string $domain,
		string $new_thumbprint
	)
	{
		$elas_intersystem = $this->cache->get('elas_interlets_domains');

		if (!isset($elas_intersystem[$domain]))
		{
			return;
		}

		foreach($elas_intersystem[$domain] as $schema => $ary)
		{
			if (!isset($ary['group_id']))
			{
				continue;
			}

			$params = [
				'schema'	=> $schema,
				'group_id'	=> $ary['group_id'],
			];

			$this->typeahead->set_thumbprint(
				'elas_intersystem_accounts',
				$params,
				$new_thumbprint
			);
		}
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
