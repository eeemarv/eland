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

	public function underline(string $input, string $char = '-'):string
	{
		$len = strlen($input);
		return $input . "\r\n" . str_repeat($char, $len);
	}

	public function replace_when_zero(int $input, $replace = null):string
	{
		return $input === 0 ? $replace : $input;
	}
}
