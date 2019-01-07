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
			new \Twig_Filter('sec_format', 'twig\\date_format::get_sec'),
			new \Twig_Filter('min_format', 'twig\\date_format::get_min'),
			new \Twig_Filter('day_format', 'twig\\date_format::get_day'),
			new \Twig_Filter('date_format_from_unix', 'twig\\date_format::get_from_unix'),
		];
	}

	public function getFunctions()
	{
		return [
//			new \Twig_Function('distance_p', 'twig\\distance::format_p'),
			new \Twig_Function('datepicker_format', 'twig\\date_format::datepicker_format'),
			new \Twig_Function('datepicker_placeholder', 'twig\\date_format::datepicker_placeholder'),
			new \Twig_Function('config', 'twig\\config::get'),
			new \Twig_Function('s3_url', 'twig\\s3_url::get'),
			new \Twig_Function('s3_link_open', 'twig\\s3_url::get_link_open'),
			new \Twig_Function('base_url', 'twig\\base_url::get'),
			new \Twig_Function('base_link_open', 'twig\\base_url::get_link_open'),
			new \Twig_Function('mail_url', 'twig\\mail_url::get'),
			new \Twig_Function('mail_link_open', 'twig\\mail_url::get_link_open'),
			new \Twig_Function('account', 'twig\\account::get'),
			new \Twig_Function('user_fullname', 'twig\\account::get_fullname'),
			new \Twig_Function('username', 'twig\\account::get_name'),
			new \Twig_Function('account_code', 'twig\\account::get_code'),
			new \Twig_Function('account_balance', 'twig\\account::get_balance'),
			new \Twig_Function('account_min', 'twig\\account::get_min'),
			new \Twig_Function('account_max', 'twig\\account::get_max'),
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
