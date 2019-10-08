<?php declare(strict_types=1);

namespace App\Twig;

use service\assets as service_assets;

class assets
{
	protected $service_assets;

	public function __construct(service_assets $service_assets)
	{
		$this->service_assets = $service_assets;
	}

	public function get(string $name):string
	{
		return $this->service_assets->get($name);
	}

	public function get_ary(string $type):array
	{
		return $this->service_assets->get_ary($type);
	}
}
