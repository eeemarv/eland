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

	public function get_options():array
	{
		return $this->date_format_service->get_options();
	}

	public function get_from_unix(
		int $unix,
		string $precision,
		string $schema
	):string
	{
		return $this->date_format_service->get_from_unix($unix, $precision, $schema);
	}

	public function get(
		string $ts,
		string $precision,
		string $schema
	):string
	{
		return $this->date_format_service->get($ts, $precision, $schema);
	}

	public function get_sec(
		string $ts,
		string $schema
	):string
	{
		return $this->date_format_service->get($ts, 'sec', $schema);
	}

	public function get_min(
		string $ts,
		string $schema
	):string
	{
		return $this->date_format_service->get($ts, 'min', $schema);
	}

	public function get_day(
		string $ts,
		string $schema
	):string
	{
		return $this->date_format_service->get($ts, 'day', $schema);
	}

	public function get_td(
		string $ts,
		string $precision,
		string $schema):string
	{
		$time = strtotime($ts . ' UTC');

		$out = '<td data-value="' . $time . '">';
		$out .= $this->date_format_service->get_from_unix($time, $precision, $schema);
		$out .= '</td>';

		return $out;
	}
}
