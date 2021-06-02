<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\ConfigService;
use Twig\Extension\RuntimeExtensionInterface;

class ConfigRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected ConfigService $config_service
	)
	{
	}

	public function get_str(string $path, string $schema)
	{
		return $this->config_service->get_str($path, $schema);
	}

	public function get_bool(string $path, string $schema)
	{
		return $this->config_service->get_bool($path, $schema);
	}

	public function get_int(string $path, string $schema)
	{
		return $this->config_service->get_int($path, $schema);
	}

	public function get_ary(string $path, string $schema)
	{
		return $this->config_service->get_ary($path, $schema);
	}
}
