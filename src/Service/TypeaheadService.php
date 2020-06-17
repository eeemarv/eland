<?php declare(strict_types=1);

namespace App\Service;

use Predis\Client as Predis;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TypeaheadService
{
	const ROUTE_PREFIX = 'typeahead_';
	const STORE_THUMBPRINT_PREFIX = 'typeahead_thumbprint_';
	const STORE_DATA_PREFIX = 'typeahead_data_';
	const TTL_THUMBPRINT = 5184000; // 60 days
	const TTL_DATA = 5184000; // 60 days
	const TTL_CLIENT = 169200; // 2 days
	const GROUP_USERS = 'users';
	const GROUP_ACCOUNTS = 'accounts';
	const GROUP_LOG_TYPES = 'log-types';
	const GROUP_DOC_MAP_NAMES = 'doc-map-names';

	protected Predis $predis;
	protected LoggerInterface $logger;
	protected UrlGeneratorInterface $url_generator;
	protected PageParamsService $pp;

	protected array $build_ary;

	public function __construct(
		Predis $predis,
		LoggerInterface $logger,
		UrlGeneratorInterface $url_generator,
		PageParamsService $pp
	)
	{
		$this->predis = $predis;
		$this->logger = $logger;
		$this->url_generator = $url_generator;
		$this->pp = $pp;
	}

	public function ini():self
	{
		$this->fetch_ary = [];
		return $this;
	}

	public function add(
		string $typeahead_route,
		array $params,
		int $ttl_client = self::TTL_CLIENT
	):self
	{
		if (!isset($this->fetch_ary))
		{
			return $this;
		}

		$thumbprint_key = $this->get_thumbprint_key($params);
		$thumbprint_field = $this->get_thumbprint_field($typeahead_route, $params);
		$group_thumbprint = $this->predis->hget($thumbprint_key, $thumbprint_field);

		if ($group_thumbprint)
		{
			[$group, $thumbprint] = explode('_', $group_thumbprint);

			if (strlen($thumbprint) !== 8 || $ttl_client === 0)
			{
				unset($thumbprint);
				$this->predis->hdel($thumbprint_key, $thumbprint_field);
			}
		}

		if (!isset($thumbprint))
		{
			$hash_rnd = hash('crc32b', random_bytes(4));
			$thumbprint = substr_replace($hash_rnd, '00', 0, 2);

			$this->logger->debug('typeahead thumbprint reset ' .
				$thumbprint . ' for ' . $thumbprint_key . ' ' .
				$thumbprint_field, ['schema' => $this->pp->schema()]);
		}

		$path = $this->get_path($typeahead_route, $params, $thumbprint);
		$schema = $this->get_schema($params);
		$cache_key = $schema . '_' . $thumbprint_field;

		$this->fetch_ary[] = [
			'path'			=> $path,
			'thumbprint'	=> $thumbprint,
			'cache_key'		=> $cache_key,
			'ttl_client'	=> $ttl_client,
		];

		return $this;
	}

	public function str(array $process_ary = []):string
	{
		$return_ary = array_merge(['fetch' => $this->fetch_ary], $process_ary);
		unset($fetch_ary);
		return json_encode($return_ary);
	}

	public function clear(string $group):void
	{
		$data_key = $this->get_data_key([]);
		$this->predis->del($data_key);

		$thumbprint_key = $this->get_thumbprint_key([]);
		$hall = $this->predis->hgetall($thumbprint_key);
		$fields_to_delete = [];

		foreach($hall as $field => $group_thumbprint)
		{
			if (strpos($group_thumbprint, $group) === 0)
			{
				$fields_to_delete[] = $field;
			}
		}

		if (!count($fields_to_delete))
		{
			return;
		}

		$this->predis->hdel($thumbprint_key, $fields_to_delete);
	}

	protected function get_thumbprint_key(
		array $params
	):string
	{
		$key = self::STORE_THUMBPRINT_PREFIX;
		$key .= $params['remote_schema'] ?? $this->pp->schema();
		return $key;
	}

	protected function get_schema(
		array $params
	):string
	{
		return $params['remote_schema'] ?? $this->pp->schema();
	}

	protected function get_data_key(
		array $params
	):string
	{
		$key = self::STORE_DATA_PREFIX;
		$key .= $params['remote_schema'] ?? $this->pp->schema();
		return $key;
	}

	protected function get_current_typeahead_route():string
	{
		return str_replace(self::ROUTE_PREFIX, '', $this->pp->route());
	}

	protected function get_thumbprint_field(
		string $typeahead_route,
		array $params
	):string
	{
		unset($params['remote_schema']);
		ksort($params);
		$key_param = implode('_', $params);
		$key = $typeahead_route;
		$key .= $key_param === '' ? '' : '_' . $key_param;
		return $key;
	}

	protected function get_path(string $typeahead_route, array $params, string $thumbprint):string
	{
		return $this->url_generator->generate(
			self::ROUTE_PREFIX . $typeahead_route,
			array_merge($this->pp->ary(), $params, ['thumbprint' => $thumbprint]),
			UrlGeneratorInterface::ABSOLUTE_PATH);
	}

	public function get_cached_data(
		string $thumbprint,
		array $params
	):?string
	{
		$data_key = $this->get_data_key($params);
		return $this->predis->hget($data_key, $thumbprint);
	}

	public function set_thumbprint(
		string $group,
		string $current_thumbprint,
		array $data,
		array $params
	):void
	{
		$json = json_encode($data);
		$new_thumbprint = hash('crc32b', $json);

		if ($new_thumbprint === $current_thumbprint)
		{
			error_log('current thumbprint still valid ' . $new_thumbprint);
			return;
		}

		$data_key = $this->get_data_key($params);
		$this->predis->hset($data_key, $new_thumbprint, $json);
		$this->predis->expire($data_key, self::TTL_DATA);
		$this->predis->hdel($data_key, $current_thumbprint);

		$typeahead_route = $this->get_current_typeahead_route();
		$thumbprint_key = $this->get_thumbprint_key($params);
		$thumbprint_field = $this->get_thumbprint_field($typeahead_route, $params);

		$this->predis->hset($thumbprint_key, $thumbprint_field, $group . '_' . $new_thumbprint);
		$this->predis->expire($thumbprint_key, self::TTL_THUMBPRINT);
	}
}
