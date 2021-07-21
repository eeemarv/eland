<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class FlattenArrayExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFilter('flatten_array', [QueryRuntime::class, 'get_flatten_array']),
		];
	}
}
