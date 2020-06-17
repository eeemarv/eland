<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class RDefaultExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('r_default', [RDefaultRuntime::class, 'get']),
		];
	}
}
