<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PaginationExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('pagination', [PaginationRuntime::class, 'get'], ['is_safe' => ['html']]),
		];
	}
}
