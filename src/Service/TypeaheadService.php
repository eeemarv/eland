<?php declare(strict_types=1);

namespace App\Service;

use Predis\Client as Predis;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Service\SystemsService;
use App\Service\AssetsService;

class TypeaheadService
{
	const TTL = 5184000; // 60 days

	protected Predis $predis;
	protected LoggerInterface $logger;
	protected UrlGeneratorInterface $url_generator;
	protected SystemsService $systems_service;
	protected AssetsService $assets_service;

	protected array $build_ary;
	protected bool $assets_included = false;

	public function __construct(
		Predis $predis,
		LoggerInterface $logger,
		UrlGeneratorInterface $url_generator,
		SystemsService $systems_service,
		AssetsService $assets_service
	)
	{
		$this->predis = $predis;
		$this->logger = $logger;
		$this->url_generator = $url_generator;
		$this->systems_service = $systems_service;
		$this->assets_service = $assets_service;
	}

	public function ini(array $pp_ary):self
	{
		$this->build_ary = ['pp_ary' => $pp_ary];
		return $this;
	}

	public function add(string $route, array $params):self
	{
		if (!isset($this->build_ary))
		{
			return $this;
		}

		$this->build_ary['paths'] ??= [];

		$this->build_ary['paths'][] = [
			'route'		=> $route,
			'params'	=> $params,
		];

		return $this;
	}

	public function str(array $process_ary = []):string
	{
		if (!isset($this->build_ary)
			|| !isset($this->build_ary['pp_ary'])
			|| !isset($this->build_ary['paths']))
		{
			return '';
		}

		$pp_ary = $this->build_ary['pp_ary'];
		$paths = $this->build_ary['paths'];

		$out = array_merge($process_ary, [
			'fetch' => [],
		]);

		foreach($paths as $p)
		{
			$path = $this->get_path($p['route'], $pp_ary, $p['params']);
			$cache_key = $this->get_thumbprint_key($p['route'], $pp_ary, $p['params']);
			$thumbprint = $this->get_thumbprint_by_key($cache_key, $pp_ary);

			$out['fetch'][] = [
				'thumbprint'	=> $thumbprint,
				'cacheKey'		=> $cache_key,
				'path'			=> $path,
			];
		}

		unset($this->build_ary);

		if (!$this->assets_included)
		{
			$this->assets_service->add(['typeahead', 'typeahead.js']);
			$this->assets_included = true;
		}

		return json_encode($out);
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
			'system'		=> $params_context['system'],
			'role_short'	=> 'a',
		];

		$key_path = $this->get_path($typeahead_route, $key_pp_ary, $params);

		$key = strtr($key_path, [
			'/a/'			=> '_',
			'/'				=> '_',
			'-'				=> '_',
		]);

		return ltrim($key, '_');
	}

	protected function get_thumbprint_by_key(
		string $key,
		array $params_context
	):string
	{
		$thumbprint = $this->predis->get($key);

		if (!$thumbprint)
		{
			$thumbprint = 'renew-' . crc32(microtime());

			$this->logger->debug('typeahead thumbprint ' .
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
		$this->predis->del($key);

		$this->logger->debug('typeahead delete thumbprint for '
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
		$system = $this->systems_service->get_system($schema);

		if (!$system)
		{
			return;
		}

		$params_context = [
			'_locale'		=> 'nl',
			'system'		=> $system,
			'role_short'	=> 'a',
		];

		$this->delete_thumbprint($typeahead_route, $params_context, $params);
	}

	protected function get_log_params(array $params_context):array
	{
		if (isset($params_context['system']))
		{
			$schema = $this->systems_service->get_schema($params_context['system']);

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
		if ($new_thumbprint !== $this->predis->get($key))
		{
			$this->predis->set($key, $new_thumbprint);

			$log_params = [];

			if ($schema && $this->systems_service->get_systems($schema))
			{
				$log_params['schema'] = $schema;
			}

			$this->logger->debug('typeahead: new thumbprint ' .
				$new_thumbprint .
				' for ' . $key,
				$log_params
			);
		}

		$this->predis->expire($key, self::TTL);
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
			$schema = $this->systems_service->get_schema($params_context['system']);
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
		$system = $this->systems_service->get_system($schema);

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
