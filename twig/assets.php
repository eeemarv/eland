<?php declare(strict_types=1);

namespace twig;

use service\assets as service_assets;

class assets
{
	protected $service_assets;

	public function __construct(service_assets $service_assets)
	{
		$this->service_assets = $service_assets;
	}

	public function get_ary(string $type):array
	{
		return $this->service_assets->get_ary($type);
	}
}
