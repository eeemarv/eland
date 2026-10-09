<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\AssetsService;
use Twig\Attribute\AsTwigFunction;

class AssetsExtension
{
	public function __construct(
		private readonly AssetsService $assets_service
	)
	{
	}

  #[AsTwigFunction(name: 'assets')]
	public function get(string $name):string
	{
		return $this->assets_service->get($name);
	}

  #[AsTwigFunction(name: 'assets_add')]
	public function add(array $asset_ary):string
	{
		$this->assets_service->add($asset_ary);
		return '';
	}

  #[AsTwigFunction(name: 'assets_add_print_css')]
	public function add_print_css(array $asset_ary):string
	{
		$this->assets_service->add_print_css($asset_ary);
		return '';
	}

  #[AsTwigFunction(name: 'assets_ary')]
	public function get_ary(string $type):array
	{
		return $this->assets_service->get_ary($type);
	}
}
