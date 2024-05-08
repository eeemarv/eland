<?php declare(strict_types=1);

namespace App\Service;

use App\Cache\ResponseCache;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TypeaheadService
{
	const ROUTE_PREFIX = 'typeahead_';
	const TTL_CLIENT = 172800; // 2 days

	protected PageParamsService $pp;
	protected array $fetch_ary;

	public function __construct(
		protected ResponseCache $response_cache,
		protected UrlGeneratorInterface $url_generator
	)
	{
	}

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

		$thumbprint_key = $this->get_thumbprint_key($typeahead_route, $params);

		$thumbprint = $this->response_cache->get_thumbprint_from_key($thumbprint_key, $schema);

		$path = $this->url_generator->generate(
			self::ROUTE_PREFIX . $typeahead_route, [
				...$this->pp->ary(),
				...$params,
				'thumbprint' => $thumbprint,
			],
			UrlGeneratorInterface::ABSOLUTE_PATH);

		$this->fetch_ary[] = [
			'key'			=> $thumbprint_key,
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

	/**
	 * For use in Form Types (Already escaped)
	 */
	public function str_raw(array $process_ary = []):string
	{
		$return_ary = [
			'fetch' => $this->fetch_ary,
			...$process_ary,
		];

		unset($this->fetch_ary);

		return json_encode($return_ary);
	}

	protected function get_thumbprint_key(
		string $typeahead_route,
		array $params
	):string
	{
		unset($params['remote_schema']);
		ksort($params);
		$key_param = implode('_', $params);
		$key = $typeahead_route;
		$key .= $key_param === '' ? '' : '_' . $key_param;
		return 'typeahead_' . $key;
	}

	public function get_cached_response_body(
		string $thumbprint,
		PageParamsService $pp,
		array $params
	):string|false
	{
		$schema = $params['remote_schema'] ?? $pp->schema();

		$typeahead_route = str_replace(self::ROUTE_PREFIX, '', $pp->route());

		$thumbprint_key = $this->get_thumbprint_key($typeahead_route, $params);

		return $this->response_cache->get_response_body($thumbprint, $thumbprint_key, $schema);
	}

	public function store_response_body(
		string $response_body,
		PageParamsService $pp,
		array $params
	):void
	{
		$schema = $params['remote_schema'] ?? $pp->schema();

		$typeahead_route = str_replace(self::ROUTE_PREFIX, '', $pp->route());
		$thumbprint_key = $this->get_thumbprint_key($typeahead_route, $params);

		$this->response_cache->store_response_body($thumbprint_key, $schema, $response_body);
	}
}
