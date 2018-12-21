<?php

namespace twig;

use service\date_format_cache;
use Symfony\Component\HttpFoundation\RequestStack;

class web_date
{
	private $date_format_cache;
	private $schema;
	private $locale;

	private $format = [];

	public function __construct(
		date_format_cache $date_format_cache,
		RequestStack $request_stack
	)
	{
		$this->date_format_cache = $date_format_cache;
		$request = $request_stack->getCurrentRequest();
		$this->locale = $request->getLocale();
		$this->schema = $request->attributes->get('schema');	
	}

	public function get(string $ts, string $precision):string
	{
		$time = strtotime($ts . ' UTC');

		if (!isset($this->format[$precision]))
		{
			$this->format[$precision] = $this->date_format_cache
				->get($precision, $this->locale, $this->schema);
		}

		return strftime($this->format[$precision], $time);
	}

	public function get_format(string $precision):string 
	{
		if (!isset($this->format[$precision]))
		{
			$this->format[$precision] = $this->date_format_cache
				->get($precision, $this->locale, $this->schema);
		}

		return $this->format[$precision];
	}
}
