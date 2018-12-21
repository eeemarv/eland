<?php

namespace twig;

class extension extends \Twig_Extension
{
	public function getFilters()
	{
		return [
			new \Twig_Filter('underline', [$this, 'underline']),
			new \Twig_Filter('replace_when_zero', [$this, 'replace_when_zero']),
			new \Twig_Filter('date_format', 'twig\\date_format::get'),
			new \Twig_Filter('web_date', 'twig\\web_date::get'),
			new \Twig_Filter('web_user', 'twig\\web_user::get'),
			new \Twig_Filter('access', 'twig\\access::get'),
			new \Twig_Filter('view', 'twig\\view::get'),
		];
	}

	public function getFunctions()
	{
		return [
			new \Twig_Function('distance_p', 'twig\\distance::format_p'),
			new \Twig_Function('datepicker_format', 'twig\\datepicker::get_format'),
			new \Twig_Function('datepicker_placeholder', 'twig\\datepicker::get_placeholder'),
			new \Twig_Function('config', 'twig\\config::get'),
		];
	}

	public function underline(string $input, string $char = '-')
	{
		$len = strlen($input);
		return $input . "\r\n" . str_repeat($char, $len);
	}

	public function replace_when_zero(int $input, $replace = null)
	{
		return $input === 0 ? $replace : $input;
	}
}
