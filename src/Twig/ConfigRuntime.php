<?php declare(strict_types=1);

namespace App\Twig;

use App\Cache\ConfigCache;
use Twig\Extension\RuntimeExtensionInterface;

class ConfigRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected ConfigCache $config_cache
	)
	{
	}

	public function get_str(string $path, string $schema)
	{
		return $this->config_cache->get_str($path, $schema);
	}

	public function get_bool(string $path, string $schema)
	{
		return $this->config_cache->get_bool($path, $schema);
	}

	public function get_int(string $path, string $schema)
	{
		return $this->config_cache->get_int($path, $schema);
	}

	public function get_ary(string $path, string $schema)
	{
		return $this->config_cache->get_ary($path, $schema);
	}
}
