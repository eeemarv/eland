<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BtnNavExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('btn_nav', [BtnNavRuntime::class, 'get'], ['is_safe' => ['html']]),
		];
	}
}