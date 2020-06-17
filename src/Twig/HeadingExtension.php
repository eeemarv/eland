<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class HeadingExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('heading', [HeadingRuntime::class, 'get_h1'], ['is_safe' => ['html']]),
			new TwigFunction('heading_sub', [HeadingRuntime::class, 'get_sub'], ['is_safe' => ['html']]),
		];
	}
}
