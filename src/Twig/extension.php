<?php declare(strict_types=1);

namespace App\Twig;

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
			new \Twig_Function('datepicker_format', 'twig\\date_format::datepicker_format'),
			new \Twig_Function('datepicker_placeholder', 'twig\\date_format::datepicker_placeholder'),
			new \Twig_Function('config', 'twig\\config::get'),
			new \Twig_Function('s3_url', 'twig\\s3_url::get'),
			new \Twig_Function('s3_link_open', 'twig\\s3_url::get_link_open'),
			new \Twig_Function('context_url', 'twig\\link_url::context_url'),
			new \Twig_Function('context_url_open', 'twig\\link_url::context_url_open'),
			new \Twig_Function('system', 'twig\\system::get'),
			new \Twig_Function('account', 'twig\\account::get'),
			new \Twig_Function('user_fullname', 'twig\\account::get_fullname'),
			new \Twig_Function('username', 'twig\\account::get_name'),
			new \Twig_Function('account_code', 'twig\\account::get_code'),
			new \Twig_Function('account_balance', 'twig\\account::get_balance'),
			new \Twig_Function('account_min', 'twig\\account::get_min'),
			new \Twig_Function('account_max', 'twig\\account::get_max'),
			new \Twig_Function('mpp_ary', 'twig\\mpp_ary::get', ['needs_context' => true]),
			new \Twig_Function('mpp_anon_ary', 'twig\\mpp_ary::get_anon', ['needs_context' => true]),
			new \Twig_Function('mpp_admin_ary', 'twig\\mpp_ary::get_admin', ['needs_context' => true]),
			new \Twig_Function('assets', 'twig\\assets::get'),
			new \Twig_Function('assets_ary', 'twig\\assets::get_ary'),
			new \Twig_Function('access', 'twig\\access::get'),
			new \Twig_Function('heading', 'twig\\heading::get_h1', ['is_safe' => ['html']]),
			new \Twig_Function('heading_sub', 'twig\\heading::get_sub', ['is_safe' => ['html']]),
			new \Twig_Function('btn_top', 'twig\\btn_top::get', ['is_safe' => ['html']]),
			new \Twig_Function('btn_nav', 'twig\\btn_nav::get', ['is_safe' => ['html']]),
			new \Twig_Function('pagination', 'twig\\pagination::get', ['is_safe' => ['html']]),
			new \Twig_Function('pp_role', 'twig\\pp_role::has_role'),
			new \Twig_Function('pp_ary', 'twig\\pp_ary::get'),
			new \Twig_Function('r_default', 'twig\\r_default::get'),
			new \Twig_Function('menu_sidebar', 'twig\\menu::get_sidebar'),
			new \Twig_Function('menu_nav_admin', 'twig\\menu::get_nav_admin'),
			new \Twig_Function('menu_nav_user', 'twig\\menu_nav_user::get_nav_user'),
			new \Twig_Function('menu_nav_logout', 'twig\\menu_nav_user::get_nav_logout'),
			new \Twig_Function('menu_nav_system', 'twig\\menu_nav_system::get_nav_system'),
			new \Twig_Function('has_menu_nav_system', 'twig\\menu_nav_system::has_nav_system'),
			new \Twig_Function('s_role', 'twig\\s_role::has_role'),
			new \Twig_Function('get_s_id', 'twig\\s_role::get_s_id'),
			new \Twig_Function('get_s_schema', 'twig\\s_role::get_s_schema'),
			new \Twig_Function('is_s_master', 'twig\\s_role::is_s_master'),
			new \Twig_Function('is_s_elas_guest', 'twig\\s_role::is_s_elas_guest'),
			new \Twig_Function('is_s_system_self', 'twig\\s_role::is_s_system_self'),
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