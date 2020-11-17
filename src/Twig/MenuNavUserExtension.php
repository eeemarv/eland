<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MenuNavUserExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('menu_nav_user', [MenuNavUserRuntime::class, 'get_nav_user']),
			new TwigFunction('menu_nav_logout', [MenuNavUserRuntime::class, 'get_nav_logout']),
		];
	}
}
