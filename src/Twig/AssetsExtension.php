<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AssetsExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('assets', [AssetsRuntime::class, 'get']),
			new TwigFunction('assets_add', [AssetsRuntime::class, 'add']),
			new TwigFunction('assets_add_print_css', [AssetsRuntime::class, 'add_print_css']),
			new TwigFunction('assets_add_var_css', [AssetsRuntime::class, 'add_var_css']),
			new TwigFunction('assets_ary', [AssetsRuntime::class, 'get_ary']),
		];
	}
}
