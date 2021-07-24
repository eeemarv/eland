<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class VarRouteExtension extends AbstractExtension
{
	public function getFilters():array
	{
		return [
			new TwigFilter('var_route', [VarRouteRuntime::class, 'get']),
			new TwigFilter('fallback_route', [VarRouteRuntime::class, 'get_fallback']),
		];
	}
}
