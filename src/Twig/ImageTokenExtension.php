<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ImageTokenExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('image_token', [ImageTokenRuntime::class, 'gen']),
		];
	}
}
