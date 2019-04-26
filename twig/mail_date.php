<?php

namespace twig;

use service\config;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class mail_date
{
	private $config;
	private $translator;

	private $format;
	private $format_ary = [];

	/**
	 *
	 */

	public function __construct(config $config, TranslatorInterface $translator)
	{
		$this->config = $config;
		$this->translator = $translator;

/*
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
*/
	}

	public function get($context, $ts = false, $precision = 'min')
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
}
