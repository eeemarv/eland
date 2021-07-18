<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MenuExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('menu_nav_admin', [MenuRuntime::class, 'get_nav_admin']),
		];
	}
}
