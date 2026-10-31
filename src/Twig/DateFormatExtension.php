<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\DateFormatService;
use DateTimeZone;
use IntlDateFormatter;
use RuntimeException;
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

  #[AsTwigFilter(name: 'date_format')]
  public function date_format(
    string $timestamp,
  ):string|null
  {
    if (!isset($timestamp))
    {
      return null;
    }
    return $this->date_format_service->date_format($timestamp);
  }

  #[AsTwigFilter(name: 'datetime_format')]
  public function datetime_format(
    string $timestamp,
  ):string|null
  {
    if (!isset($timestamp))
    {
      return null;
    }
    return $this->date_format_service->datetime_format($timestamp);
  }

  #[AsTwigFilter(name: 'format_day')]
  public function format_day(
    string $timestamp,
  ):string|null
  {
    if (!isset($timestamp))
    {
      return null;
    }
    return $this->date_format_service->format_day($timestamp);
  }

  #[AsTwigFilter(name: 'format_min')]
  public function format_min(
    string $timestamp,
  ):string|null
  {
    if (!isset($timestamp))
    {
      return null;
    }
    return $this->date_format_service->format_min($timestamp);
  }

  #[AsTwigFilter(name: 'format_sec')]
  public function format_sec(
    string $timestamp,
  ):string|null
  {
    if (!isset($timestamp))
    {
      return null;
    }
    return $this->date_format_service->format_sec($timestamp);
  }
}
