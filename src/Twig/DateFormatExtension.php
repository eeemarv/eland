<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class DateFormatExtension extends AbstractExtension
{
	public function getFilters():array
	{
		return [
			new TwigFilter('date_format', [DateFormatRuntime::class, 'get'], ['needs_context' => true]),
		];
	}

	public function getFunctions():array
	{
		return [
			new TwigFunction('datepicker_format', [DateFormatRuntime::class, 'datepicker_format']),
			new TwigFunction('datepicker_placeholder', [DateFormatRuntime::class, 'datepicker_placeholder']),
		];
	}
}
