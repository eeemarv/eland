<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

class LinkExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('link', [LinkRuntime::class, 'link']),
		];
	}

	public function getFilters():array
	{
		return [
			new TwigFilter('link', [LinkRuntime::class, 'link_filter']),
		];
	}
}
