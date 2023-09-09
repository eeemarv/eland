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

	public function add(array $asset_ary):string
	{
		$this->assets_service->add($asset_ary);
		return '';
	}

	public function add_print_css(array $asset_ary):string
	{
		$this->assets_service->add_print_css($asset_ary);
		return '';
	}

	public function add_var_css(string $thumbprint_key, string $schema):string
	{
		$this->assets_service->add_var_css($thumbprint_key, $schema);
		return '';
	}

	public function get_ary(string $type):array
	{
		return $this->assets_service->get_ary($type);
	}
}
