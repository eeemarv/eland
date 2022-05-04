<?php declare(strict_types=1);

namespace App\Service;

use Redis;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TypeaheadService
{
	const ROUTE_PREFIX = 'typeahead_';
	const STORE_KEY = 'typeahead_%schema%';
	const TTL_STORE = 5184000; // 60 days
	const TTL_CLIENT = 172800; // 2 days

	protected PageParamsService $pp;
	protected array $fetch_ary;

	public function __construct(
		protected Redis $predis,
		protected LoggerInterface $logger,
		protected UrlGeneratorInterface $url_generator
	)
	{
	}

	/**
	 * get links
	 */
	public function ini(PageParamsService $pp):self
	{
		$this->pp = $pp;
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

		$schema = $params['remote_schema'] ?? $this->pp->schema();

		$store_key = strtr(self::STORE_KEY, [
			'%schema%' => $schema,
		]);

		$field = $this->get_field_for_thumbprint($typeahead_route, $params);
		$thumbprint = $this->predis->hget($store_key, $field);

		if ($thumbprint)
		{
			if (strlen($thumbprint) !== 8 || $ttl_client === 0)
			{
				unset($thumbprint);
				$this->predis->hdel($store_key, $field);
			}
		}

		if (!$thumbprint)
		{
			$hash_rnd = hash('crc32b', random_bytes(4));
			$thumbprint = substr_replace($hash_rnd, '-', rand(1, 6), 1);

			$this->logger->debug('typeahead thumbprint reset ' .
				$thumbprint . ' for ' .
				$field, ['schema' => $schema]);
		}

		$path = $this->url_generator->generate(
			self::ROUTE_PREFIX . $typeahead_route, [
				...$this->pp->ary(),
				...$params,
				'thumbprint' => $thumbprint,
			],
			UrlGeneratorInterface::ABSOLUTE_PATH);

		$this->fetch_ary[] = [
			'path'			=> $path,
			'thumbprint'	=> $thumbprint,
			'ttl_client'	=> $ttl_client,
		];

		return $this;
	}

	public function str(array $process_ary = []):string
	{
		$return_ary = [
			'fetch' => $this->fetch_ary,
			...$process_ary,
		];

		unset($this->fetch_ary);

		return htmlspecialchars(json_encode($return_ary));
	}

	// escaping already in forms
	public function str_raw(array $process_ary = []):string
	{
		$return_ary = [
			'fetch' => $this->fetch_ary,
			...$process_ary,
		];

		unset($this->fetch_ary);

		return json_encode($return_ary);
	}

	/**
	 *
	 */
	public function clear_cache(string $schema):void
	{
		$store_key = strtr(self::STORE_KEY, [
			'%schema%' => $schema,
		]);

		$this->predis->del($store_key);
	}

	/**
	 *
	 */
	protected function get_field_for_thumbprint(
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

	/**
	 * typeahead routes
	 */
	public function get_cached_data(
		string $thumbprint,
		PageParamsService $pp,
		array $params
	):string|bool
	{
		$store_key = strtr(self::STORE_KEY, [
			'%schema%' => $params['remote_schema'] ?? $pp->schema(),
		]);

		$typeahead_route = str_replace(self::ROUTE_PREFIX, '', $pp->route());

		$compare_thumbprint = $this->get_field_for_thumbprint($typeahead_route, $params);

		if ($compare_thumbprint !== $thumbprint)
		{
			return false;
		}

		$data = $this->predis->hget($store_key, $thumbprint);

		if (!isset($data) || !$data)
		{
			return false;
		}

		return $data;
	}

	public function set_thumbprint(
		string $current_thumbprint,
		string $data,
		PageParamsService $pp,
		array $params
	):void
	{
		$new_thumbprint = hash('crc32b', $data);

		if ($new_thumbprint === $current_thumbprint)
		{
			error_log('current thumbprint still valid ' . $new_thumbprint);
			return;
		}

		$schema = $params['remote_schema'] ?? $pp->schema();

		$store_key = strtr(self::STORE_KEY, [
			'%schema%' => $schema,
		]);

		$this->predis->hset($store_key, $new_thumbprint, $data);
		$this->predis->hdel($store_key, $current_thumbprint);

		$typeahead_route = str_replace(self::ROUTE_PREFIX, '', $pp->route());
		$thumbprint_field = $this->get_field_for_thumbprint($typeahead_route, $params);

		$this->predis->hset($store_key, $thumbprint_field, $new_thumbprint);
		$this->predis->expire($store_key, self::TTL_STORE);

		$this->logger->debug('typeahead NEW thumbprint SET (calculated) ' .
			$new_thumbprint . ' for ' .
			$thumbprint_field, ['schema' => $schema]);
	}
}
