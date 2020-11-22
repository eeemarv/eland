<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class StaticContentExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('static_content_has_local', [StaticContentRuntime::class, 'get_local']),
			new TwigFunction('static_content_local', [StaticContentRuntime::class, 'get_local'], ['is_safe' => ['html']]),
			new TwigFunction('static_content_has_global', [StaticContentRuntime::class, 'has_global']),
			new TwigFunction('static_content_global', [StaticContentRuntime::class, 'get_global'], ['is_safe' => ['html']]),
		];
	}
}
