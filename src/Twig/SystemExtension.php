<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SystemExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('system', [SystemRuntime::class, 'get']),
		];
	}
}
