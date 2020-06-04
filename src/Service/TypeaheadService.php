<?php declare(strict_types=1);

namespace App\Service;

use Predis\Client as Predis;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TypeaheadService
{
	const ROUTE_PREFIX = 'typeahead_';
	const STORE_THUMBPRINT_PREFIX = 'typeahead_thumbprint_';
	const STORE_DATA_PREFIX = 'typeahead_data_';
	const TTL_THUMBPRINT = 5184000; // 60 days
	const TTL_DATA = 5184000; // 60 days
	const GROUP_USERS = 'users';
	const GROUP_ACCOUNTS = 'accounts';
	const GROUP_LOG_TYPES = 'log_types';
	const GROUP_DOC_MAP_NAMES = 'doc_map_names';

	protected Predis $predis;
	protected RequestStack $request_stack;
	protected LoggerInterface $logger;
	protected UrlGeneratorInterface $url_generator;
	protected PageParamsService $pp;

	protected array $build_ary;

	public function __construct(
		Predis $predis,
		RequestStack $request_stack,
		LoggerInterface $logger,
		UrlGeneratorInterface $url_generator,
		PageParamsService $pp
	)
	{
		$this->predis = $predis;
		$this->request_stack = $request_stack;
		$this->logger = $logger;
		$this->url_generator = $url_generator;
		$this->pp = $pp;
	}

	public function ini():self
	{
		$this->fetch_ary = [];
		return $this;
	}

	public function add(string $route, array $params):self
	{
		if (!isset($this->fetch_ary))
		{
			return $this;
		}

		$path = $this->get_path($route, $params);
		$this->fetch_ary[] = $path;
		return $this;
	}

	public function str(array $process_ary = []):string
	{
		$return_ary = array_merge(['fetch' => $this->fetch_ary], $process_ary);
		unset($fetch_ary);
		return json_encode($return_ary);
	}

	protected function get_thumbprint(
		string $field,
		?string $remote_schema = null
	):string
	{
		$key = $this->get_key($remote_schema);
		$group_thumbprint = $this->predis->hget($key, $field);

		if (!$group_thumbprint)
		{
			$hash_rnd = hash('crc32b', random_bytes(4));
			$thumbprint = substr_replace($hash_rnd, '00', 0, 2);

			$this->logger->debug('typeahead thumbprint reset ' .
				$thumbprint . ' for ' . $key, ['schema' => $this->pp->schema()]);
		}

		return $thumbprint;
	}

	public function clear(string $group):void
	{
		$data_key = $this->get_data_key();
		$this->predis->del($data_key);

		$thumbprint_key = $this->get_thumbprint_key();
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

		$this->predis->hdel($key, $fields_to_delete);
	}

	public function get_schema(
		?string $remote_schema = null
	):string
	{
		return $remote_schema ?? $this->pp->schema();
	}

	public function get_thumbprint_key(
		?string $remote_schema = null
	):string
	{
		$key = self::STORE_THUMBPRINT_PREFIX;
		$key .= $remote_schema ?? $this->pp->schema();
		return $key;
	}

	public function get_data_key(
		?string $remote_schema = null
	):string
	{
		$key = self::STORE_DATA_PREFIX;
		$key .= $remote_schema ?? $this->pp->schema();
		return $key;
	}

	public function get_current_typeahead_route():string
	{
		$request = $this->request_stack->getCurrentRequest();
		$route = $request->attributes->get('_route');
		return str_replace(self::ROUTE_PREFIX, '', $route);
	}

	public function get_thumbprint_field(
		string $typeahead_route,
		?string $key_param = null
	):string
	{
		$key = $typeahead_route;
		$key .= isset($key_param) ? '_' . $key_param : '';
		return $key;
	}

	public function get_key_param(array $params):?string
	{
		if (!count($params))
		{
			return null;
		}

		ksort($params);

		return implode('_', $params);
	}

	public function get_path(string $typeahead_route, array $params):string
	{
		$key_param = $this->get_key_param($params);
		$remote_schema = $params['remote_schema'] ?? null;
		$key = $this->get_key($remote_schema);
		$thumbprint = $this->get_thumbprint($key);

		return $this->url_generator->generate(
			$typeahead_route,
			array_merge($this->pp->ary(), $params, ['thumbprint' => $thumbprint]),
			UrlGeneratorInterface::ABSOLUTE_PATH);
	}

	public function get_data(
		string $thumbprint,
		?string $remote_schema = null
	):?string
	{
		$data_key = $this->get_data_key($thumbprint, $remote_schema);
		return $this->predis->get($data_key);
	}

	public function calc_thumbprint(
		string $group,
		string $current_thumbprint,
		array $data,
		?string $key_param = null,
		?string $remote_schema = null
	):void
	{
		$json = json_encode($data);
		$new_thumbprint = hash('crc32b', $json);

		if ($new_thumbprint === $current_thumbprint)
		{
			error_log('current thumbprint still valid ' . $new_thumbprint);
			return;
		}

		$data_key = $this->get_data_key($remote_schema);
		$this->predis->hset($data_key, $new_thumbprint, $json);
		$this->predis->expire($data_key, self::TTL_DATA);
		$this->predis->hdel($data_key, $current_thumbprint);

		$typeahead_route = $this->get_current_typeahead_route();

		$thumbprint_key = $this->get_thumbprint_key($remote_schema);
		$thumbprint_field = $this->get_thumbprint_field($typeahead_route, $key_param);

		$this->predis->hset($thumbprint_key, $thumbprint_field, $group . '_' . $new_thumbprint);
		$this->predis->expire($thumbprint_key, self::TTL_THUMBPRINT);
	}
}
