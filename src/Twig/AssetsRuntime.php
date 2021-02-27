<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\AssetsService;
use Twig\Extension\RuntimeExtensionInterface;

class AssetsRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected AssetsService $assets_service
	)
	{
	}

	public function get(string $name):string
	{
		return $this->assets_service->get($name);
	}

	public function get_ary(string $type):array
	{
		return $this->assets_service->get_ary($type);
	}
}
