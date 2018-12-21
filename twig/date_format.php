<?php

namespace twig;

use service\config;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\TranslatorInterface;

class date_format
{
	private $request;
	private $schema;
	private $config;

	private static $format_keys = [
		'technical',
		'numerical',
		'month_abbrev',
		'month_full',
		'weekday_included',
	];

	private static $datepicker_keys = [
		'%e' => 'd',
		'%d' => 'dd',
		'%m' => 'mm',
		'%Y' => 'yyyy',
		'%b' => 'M',
		'%B' => 'MM',
		'%a' => 'D',
		'%A' => 'DD',
	];



	/*
	datepicker:
	placeholder:
	  %e: d
	  %d: dd
	  %m: mm
	  %Y: jjjj
	  %b: mnd
	  %B: maand
	  %a: (wd)
	  %A: (weekdag)
*/



	private static $formats = [
		'%Y-%m-%d %H:%M:%S' => [
			'day'	=> '%Y-%m-%d',
			'min'	=> '%Y-%m-%d %H:%M',
		],
		'%d-%m-%Y %H:%M:%S' => [
			'day'	=> '%d-%m-%Y',
			'min'	=> '%d-%m-%Y %H:%M',
		],
		'%e %b %Y, %H:%M:%S' => [
			'day'	=> '%e %b %Y',
			'min'	=> '%e %b %Y, %H:%M',
		],
		'%a %e %b %Y, %H:%M:%S' => [
			'day'	=> '%a %e %b %Y',
			'min'	=> '%a %e %b %Y, %H:%M',
		],
		'%e %B %Y, %H:%M:%S'	=> [
			'day'	=> '%e %B %Y',
			'min'	=> '%e %B %Y, %H:%M',
		],
	];

	private $format;

	private $format_ary = [];

	private $month_translate_ary = [
		['jan', 'januari'], ['feb', 'februari'], ['mrt', 'maart'],
		['apr', 'april'], ['mei', 'mei'], ['jun', 'juni'],
		['jul', 'juli'], ['aug', 'augustus'], ['sep', 'september'],
		['okt', 'oktober'], ['nov', 'november'], ['dec', 'december']
	];


		

	/**
	 *
	 */

	public function __construct(config $config, TranslatorInterface $translator, string $schema)
	{
		$this->config = $config;
		$this->translator = $translator;
		$this->schema = $schema;

		$this->format = $this->config->get('date_format', $this->schema);

		if (!$this->format)
		{
			$this->format = '%e %b %Y, %H:%M:%S';
		}

		$sec = $this->format;

		if (!isset(self::$formats[$sec]))
		{
			$sec = '%e %b %Y, %H:%M:%S';
		}

		$this->format_ary = self::$formats[$sec];
		$this->format_ary['sec'] = $sec;
	}

	/*
	 *
	 */

	public function datepicker_format()
	{
		$search = ['%e', '%d', '%m', '%Y', '%b', '%B', '%a', '%A'];
		$replace = ['d', 'dd', 'mm', 'yyyy', 'M', 'MM', 'D', 'DD'];

		return trim(str_replace($search, $replace, $this->format_ary['day']));
	}

	/**
	 *
	 */

	public function datepicker_placeholder()
	{
		$search = ['%e', '%d', '%m', '%Y', '%b', '%B', '%a', '%A'];
		$replace = ['d', 'dd', 'mm', 'jjjj', 'mnd', 'maand', '(wd)', '(weekdag)'];

		return trim(str_replace($search, $replace, $this->format_ary['day']));
	}

	/**
	 *
	 */

	public function reverse($from_datepicker)
	{
		$from_datepicker = trim($from_datepicker);

		$months_search = $months_replace = [];

		foreach ($this->month_translate_ary as $k => $m)
		{
			$months_search[] = $m[0];
			$months_search[] = $m[1];

			$months_replace[] = $k + 1;
			$months_replace[] = $k + 1;
		}

		$str = str_replace($months_search, $months_replace, $from_datepicker);

		$format = $this->format_ary['day'];

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
			return false;
		}

		$time = mktime(12, 0, 0, $parts['%m'], $parts['%d'], $parts['%Y']);

		return gmdate('Y-m-d H:i:s', $time);
	}

	/**
	 *
	 */

	public function get_options()
	{
		$options = [];

		foreach (self::$formats as $format => $prec)
		{
			$options[$format] = strftime($format);
		}

		return $options;
	}

	/**
	 *
	 */

	public function get_error($format)
	{
		if (!isset(self::$formats[$format]))
		{
			return 'Fout: dit datum- en tijdsformaat wordt niet ondersteund.';
		}

		return false;
	}

	/**
	 * to do: get schema for static method version
	 */

	public function get(string $ts, string $schema, string $precision = 'min')
	{
		static $format_ary, $format;

		$time = strtotime($ts . ' UTC');

		if (isset($this))
		{
			return strftime($this->format_ary[$precision], $time);
		}

		if (!isset($format_ary))
		{
			$format = $this->config->get('date_format', $this->schema);

			if (!$format)
			{
				$format = '%e %b %Y, %H:%M:%S';
			}

			$sec = $format;

			if (!isset(self::$formats[$sec]))
			{
				$sec = '%e %b %Y, %H:%M:%S';
			}

			$format_ary = self::$formats[$sec];
			$format_ary['sec'] = $sec;
		}

		return strftime($format_ary[$precision], $time);
	}

	/**
	 *
	 */

	public function twig_get($environment, $context, $ts = false, $precision = 'min')
	{
		static $format_ary, $format;

		$time = strtotime($ts . ' UTC');

		if (!isset($format_ary))
		{
			$format = $this->config->get('date_format');

			if (!$format)
			{
				$format = '%e %b %Y, %H:%M:%S';
			}

			$sec = $format;

			if (!isset(self::$formats[$sec]))
			{
				$sec = '%e %b %Y, %H:%M:%S';
			}

			$format_ary = self::$formats[$sec];
			$format_ary['sec'] = $sec;
		}

		return strftime($format_ary[$precision], $time);
	}


	/**
	 *
	 */

	public function get_td($ts = false, $precision = 'min')
	{
		$time = strtotime($ts . ' UTC');

		return '<td data-value="' . $time . '">' . strftime($this->format_ary[$precision], $time) . '</td>';
	}
}
