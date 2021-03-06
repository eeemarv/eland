<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MenuNavSystemExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('menu_nav_system', [MenuNavSystemRuntime::class, 'get_nav_system']),
			new TwigFunction('has_menu_nav_system', [MenuNavSystemRuntime::class, 'has_nav_system']),
		];
	}
}
