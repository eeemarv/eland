<?php

namespace twig;

use service\date_format_cache;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\TranslatorInterface;
use twig\web_date;

class datepicker
{
	private $web_date;
	private $translator;

	private $format_search = ['%e', '%d', '%m', '%Y', '%b', '%B', '%a', '%A'];
	private $format_replace = ['d', 'dd', 'mm', 'yyyy', 'M', 'MM', 'D', 'DD'];
	private $placeholder_replace = [];
	private $placeholder;

	public function __construct(
		web_date $web_date,
		TranslatorInterface $translator	
	)
	{
		$this->web_date = $web_date;
		$this->translator = $translator;	
	}

	public function get_placeholder():string
	{
		$format = $this->web_date->get_format('day');

		if (!count($this->placeholder_replace))
		{
			foreach($this->format_search as $s)
			{
				$this->placeholder_replace[] = $this->translator->trans('datepicker.placeholder.' . $s);
			}
		}

		if (!isset($this->placeholder))
		{
			$this->placeholder = str_replace($this->format_search, $this->placeholder_replace, $format);
		}

		return $this->placeholder;
	}

	public function get_format():string
	{
		$format = $this->web_date->get_format('day');

		return str_replace($this->format_search, $this->format_replace, $format);
	}
}
