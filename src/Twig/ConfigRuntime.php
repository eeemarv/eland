<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\ConfigService;
use Twig\Extension\RuntimeExtensionInterface;

class ConfigRuntime implements RuntimeExtensionInterface
{
	protected $config_service;

	public function __construct(ConfigService $config_service)
	{
		$this->config_service = $config_service;
	}

	public function get(string $key, string $schema)
	{
		return $this->config_service->get($key, $schema);
	}
}
