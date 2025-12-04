<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\DateFormatService;
use Twig\Extension\RuntimeExtensionInterface;

class DateFormatRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected DateFormatService $date_format_service
	)
	{
	}

	public function datepicker_format(string $schema):string
	{
		return $this->date_format_service->datepicker_format($schema);
	}

	public function datepicker_placeholder(string $schema):string
	{
		return $this->date_format_service->datepicker_placeholder($schema);
	}

	public function get(
    array $context,
		string $ts,
		string $precision,
    string|null $schema = null
	):string
	{
    $sch = $schema ?? $context['schema'] ?? null;
		return $this->date_format_service->get($ts, $precision, $sch);
	}
}
