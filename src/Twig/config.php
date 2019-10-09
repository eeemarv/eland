<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\config as cnfg;

class config
{
	private $config;

	public function __construct(cnfg $config)
	{
		$this->config_service = $config_service;
	}

	public function get(string $key, string $schema)
	{
		return $this->config_service->get($key, $schema);
	}
}
