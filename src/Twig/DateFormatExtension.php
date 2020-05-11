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
			new TwigFilter('date_format', [DateFormatRuntime::class, 'get']),
			new TwigFilter('sec_format', [DateFormatRuntime::class, 'get_sec']),
			new TwigFilter('min_format', [DateFormatRuntime::class, 'get_min']),
			new TwigFilter('day_format', [DateFormatRuntime::class, 'get_day']),
			new TwigFilter('date_format_from_unix', [DateFormatRuntime::class, 'get_from_unix']),
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
