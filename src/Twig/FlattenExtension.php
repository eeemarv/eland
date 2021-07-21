<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class FlattenExtension extends AbstractExtension
{
	public function getFilters():array
	{
		return [
			new TwigFilter('flatten', [FlattenRuntime::class, 'get_flatten']),
		];
	}
}
