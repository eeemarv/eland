<?php declare(strict_types=1);

namespace App\Twig;

use service\config as cnfg;

class config
{
	private $config;

	public function __construct(cnfg $config)
	{
		$this->config = $config;
	}

	public function get(string $key, string $schema)
	{
		return $this->config->get($key, $schema);
	}
}
