<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\DateFormatService;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

class DateFormatExtension
{
	public function __construct(
		private readonly DateFormatService $date_format_service
	)
	{
	}

  #[AsTwigFunction(name: 'datepicker_format')]
	public function datepicker_format(string $schema):string
	{
		return $this->date_format_service->datepicker_format($schema);
	}

  #[AsTwigFunction(name: 'datepicker_placeholder')]
	public function datepicker_placeholder(string $schema):string
	{
		return $this->date_format_service->datepicker_placeholder($schema);
	}

  #[AsTwigFilter(name: 'date_format', needsContext: true)]
	public function get(
    array $context,
		string|null $ts,
		string $precision,
    string|null $schema = null
	):string|null
	{
    if (!isset($ts))
		{
			return null;
		}
    $sch = $schema ?? $context['schema'] ?? null;
		return $this->date_format_service->get($ts, $precision, $sch);
	}
}
