<?php

namespace service;

use Predis\Client as Redis;
use Monolog\Logger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use service\systems;

class typeahead
{
	protected $redis;
	protected $monolog;
	protected $url_generator;
	protected $systems;
	const TTL = 5184000; // 60 days

	public function __construct(
		Redis $redis,
		Logger $monolog,
		UrlGeneratorInterface $url_generator,
		systems $systems
	)
	{
		$this->redis = $redis;
		$this->monolog = $monolog;
		$this->url_generator = $url_generator;
		$this->systems = $systems;
	}

	public function get(array $params_context, array $identifiers):string
	{
		$out = [];

		foreach($identifiers as $identifier)
		{
			$typeahead_route = $identifier[0];
			$params = $identifier[1];

			$path = $this->get_path($typeahead_route, $params_context, $params);
			$cache_key = $this->get_thumbprint_key($typeahead_route, $params_context, $params);
			$thumbprint = $this->get_thumbprint_by_key($cache_key, $params_context);

			$rec = [
				'thumbprint'	=> $thumbprint,
				'cacheKey'		=> $cache_key,
				'path'			=> $path,
			];

			$out[] = $rec;
		}

		return htmlspecialchars(json_encode($out));
	}

	protected function get_path(
		string $typeahead_route,
		array $params_context,
		array $params
	):string
	{
		return $this->url_generator->generate(
			'typeahead_' . $typeahead_route,
			array_merge($params_context, $params),
			UrlGeneratorInterface::ABSOLUTE_PATH);
	}

	protected function get_thumbprint_key(
		string $typeahead_route,
		array $params_context,
		array $params):string
	{
		$key_pp_ary = [
			'_locale'		=> 'nl',
			'system'		=> $params_context['system'],
			'role_short'	=> 'a',
		];

		$key_path = $this->get_path($typeahead_route, $key_pp_ary, $params);

		return strtr($key_path, [
			'/nl/'			=> '',
			'/a/'			=> '_',
			'/'				=> '_',
			'-'				=> '_',
		]);
	}

	protected function get_thumbprint_by_key(
		string $key,
		array $params_context
	):string
	{
		$thumbprint = $this->redis->get($key);

		if (!$thumbprint)
		{
			$thumbprint = 'renew-' . crc32(microtime());

			$this->monolog->debug('typeahead thumbprint ' .
				$thumbprint . ' for ' . $key,
				$this->get_log_params($params_context)
			);
		}

		return $thumbprint;
	}

	protected function get_thumbprint(
		string $typeahead_route,
		array $params_context,
		array $params
	):string
	{
		$key = $this->get_thumbprint_key($typeahead_route, $params_context, $params);

		return $this->get_thumbprint_by_key($key, $params_context);
	}

	protected function delete_thumbprint_by_key(
		string $key,
		array $params_context
	):void
	{
		$this->redis->del($key);

		$this->monolog->debug('typeahead delete thumbprint for '
			. $key, $this->get_log_params($params_context));
	}

	public function delete_thumbprint(
		string $typeahead_route,
		array $params_context,
		array $params
	):void
	{
		$key = $this->get_thumbprint_key($typeahead_route, $params_context, $params);

		$this->delete_thumbprint_by_key($key, $params_context);
	}

	public function delete_thumbprint_by_schema(
		string $typeahead_route,
		string $schema,
		array $params
	):void
	{
		$system = $this->systems->get_system($schema);

		if (!$system)
		{
			return;
		}

		$params_context = [
			'_locale'		=> 'en',
			'system'		=> $system,
			'role_short'	=> 'a',
		];

		$this->delete_thumbprint($typeahead_route, $params_context, $params);
	}

	protected function get_log_params(array $params_context):array
	{
		if (isset($params_context['system']))
		{
			$schema = $this->systems->get_schema($params_context['system']);

			if ($schema)
			{
				return ['schema' => $schema];
			}
		}

		return [];
	}

	protected function set_thumbprint_by_key(
		string $key,
		string $schema,
		string $new_thumbprint
	):void
	{
		if ($new_thumbprint !== $this->redis->get($key))
		{
			$this->redis->set($key, $new_thumbprint);

			$log_params = [];

			if ($schema && $this->systems->get_systems($schema))
			{
				$log_params['schema'] = $schema;
			}

			$this->monolog->debug('typeahead: new thumbprint ' .
				$new_thumbprint .
				' for ' . $key,
				$log_params
			);
		}

		$this->redis->expire($key, self::TTL);
	}

	public function set_thumbprint(
		string $typeahead_route,
		array $params_context,
		array $params,
		string $new_thumbprint
	):void
	{
		$key = $this->get_thumbprint_key($typeahead_route, $params_context, $params);

		$schema = '';

		if (isset($params_context['system']))
		{
			$schema = $this->systems->get_schema($params_context['system']);
		}

		$this->set_thumbprint_by_key($key, $schema, $new_thumbprint);
	}

	public function set_thumbprint_by_schema(
		string $typeahead_route,
		string $schema,
		array $params,
		string $new_thumbprint
	):void
	{
		$system = $this->systems->get_system($schema);

		if (!$system)
		{
			return;
		}

		$params_context = [
			'_locale'		=> 'en',
			'system'		=> $system,
			'role_short'	=> 'a',
		];

		$key = $this->get_thumbprint_key($typeahead_route, $params_context, $params);
		$this->set_thumbprint_by_key($key, $schema, $new_thumbprint);
	}
}
