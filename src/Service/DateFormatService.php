<?php declare(strict_types=1);

namespace App\Service;

use App\Service\ConfigService;

class DateFormatService
{
	const FORMATS = [
		'%Y-%m-%d %H:%M:%S' => [
			'day'	=> '%Y-%m-%d',
			'min'	=> '%Y-%m-%d %H:%M',
			'sec'	=> '%Y-%m-%d %H:%M:%S',
		],
		'%d-%m-%Y %H:%M:%S' => [
			'day'	=> '%d-%m-%Y',
			'min'	=> '%d-%m-%Y %H:%M',
			'sec'	=> '%d-%m-%Y %H:%M:%S',
		],
		'%e %b %Y, %H:%M:%S' => [
			'day'	=> '%e %b %Y',
			'min'	=> '%e %b %Y, %H:%M',
			'sec'	=> '%e %b %Y, %H:%M:%S',
		],
		'%a %e %b %Y, %H:%M:%S' => [
			'day'	=> '%a %e %b %Y',
			'min'	=> '%a %e %b %Y, %H:%M',
			'sec'	=> '%a %e %b %Y, %H:%M:%S',
		],
		'%e %B %Y, %H:%M:%S'	=> [
			'day'	=> '%e %B %Y',
			'min'	=> '%e %B %Y, %H:%M',
			'sec'	=> '%e %B %Y, %H:%M:%S',
		],
	];

	const NEW_FORMAT_KEY = [   // todo
		'technical' 		=> '%Y-%m-%d %H:%M:%S',
		'numerical' 		=> '%d-%m-%Y %H:%M:%S',
		'month_abbrev'		=> '%e %b %Y, %H:%M:%S',
		'month_full'		=> '%e %B %Y, %H:%M:%S',
		'weekday_included'	=> '%a %e %b %Y, %H:%M:%S',
	];

	const MONTHS_TRANS = [
		['jan', 'januari'], ['feb', 'februari'], ['mrt', 'maart'],
		['apr', 'april'], ['mei', 'mei'], ['jun', 'juni'],
		['jul', 'juli'], ['aug', 'augustus'], ['sep', 'september'],
		['okt', 'oktober'], ['nov', 'november'], ['dec', 'december']
	];

	public function __construct(
		protected ConfigService $config_service
	)
	{
	}

	protected function get_format(
		string $precision,
		string $schema
	):string
	{
		$format = $this->config_service->get_str('system.date_format', $schema);

		if (!$format || !isset(self::FORMATS[$format]))
		{
			throw new \Exception('No valid date format in date_format: ' . $format);
		}

		if (!isset(self::FORMATS[$format][$precision]))
		{
			throw new \Exception('No valid precision in date_format: ' . $precision);
		}

		return self::FORMATS[$format][$precision];
	}


	public function datepicker_format(string $schema):string
	{
		$search = ['%e', '%d', '%m', '%Y', '%b', '%B', '%a', '%A'];
		$replace = ['d', 'dd', 'mm', 'yyyy', 'M', 'MM', 'D', 'DD'];
		$format = $this->get_format('day', $schema);

		return trim(str_replace($search, $replace, $format));
	}

	public function datepicker_placeholder(string $schema):string
	{
		$search = ['%e', '%d', '%m', '%Y', '%b', '%B', '%a', '%A'];
		$replace = ['d', 'dd', 'mm', 'jjjj', 'mnd', 'maand', '(wd)', '(weekdag)'];
		$format = $this->get_format('day', $schema);

		return trim(str_replace($search, $replace, $format));
	}

	public function reverse(
		string $from_datepicker,
		string $schema
	):string
	{
		$from_datepicker = trim($from_datepicker);

		$months_search = $months_replace = [];

		foreach (self::MONTHS_TRANS as $k => $m)
		{
			$months_search[] = $m[0];
			$months_search[] = $m[1];

			$months_replace[] = $k + 1;
			$months_replace[] = $k + 1;
		}

		$str = str_replace($months_search, $months_replace, $from_datepicker);

		$format = $this->get_format('day', $schema);

		$map = [
			'%e' => '%d', '%d' => '%d', '%m' => '%m',
			'%Y' => '%Y', '%b' => '%m', '%B' => '%m',
			'%a' => '', '%A' => '',
		];

		$format = str_replace(array_keys($map), array_values($map), $format);

		$digits_expected = [
			'%d' => 2, '%m' => 2, '%Y' => 4,
		];

		$parts = [];
		$key_part = false;

		$str = str_split($str);
		$format = str_split($format);

		$s = reset($str);
		$f = reset($format);

		while ($s !== false)
		{
			if (ctype_digit((string) $s))
			{
				if (!$key_part)
				{
					while ($f != '%' && $f !== false)
					{
						$f = next($format);
					}

					$f = next($format);
					$key_part = '%' . $f;
				}

				$parts[$key_part] = isset($parts[$key_part]) ? $parts[$key_part] . $s : $s;

				$len = strlen($parts[$key_part]);

				if ($len == $digits_expected[$key_part])
				{
					$key_part = false;
				}
			}
			else
			{
				$key_part = false;
			}

			$s = next($str);
		}

		if (!isset($parts['%m']) || !isset($parts['%d']) || !isset($parts['%Y']))
		{
			return '';
		}

		$time = mktime(12, 0, 0,
			(int) $parts['%m'], (int) $parts['%d'], (int) $parts['%Y']);

		return gmdate('Y-m-d H:i:s', $time);
	}

	public function get_choices():array
	{
		$choices = [];

		foreach (self::FORMATS as $format => $prec)
		{
			$choices[strftime($format, time())] = $format;
		}

		return $choices;
	}

	public function get_error(string $format)
	{
		if (!isset(self::FORMATS[$format]))
		{
			return 'Fout: dit datum- en tijdformaat wordt niet ondersteund.';
		}

		return false;
	}

	public function get_from_unix(
		int $unix,
		string $precision,
		string $schema
	):string
	{
		$format = $this->get_format($precision, $schema);

		return strftime($format, $unix);
	}

	public function get(
		string $ts,
		string $precision,
		string $schema
	):string
	{
		if (!$ts)
		{
			return '';
		}

		return $this->get_from_unix(strtotime($ts . ' UTC'), $precision, $schema);
	}

	public function get_td(
		string $ts,
		string $precision,
		string $schema):string
	{
		$time = strtotime($ts . ' UTC');

		$out = '<td data-value="' . $time . '">';
		$out .= $this->get_from_unix($time, $precision, $schema);
		$out .= '</td>';

		return $out;
	}
}
