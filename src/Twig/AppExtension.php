<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
	public function getFilters():array
	{
		return [
			new TwigFilter('underline', [$this, 'underline']),
			new TwigFilter('replace_when_zero', [$this, 'replace_when_zero']),
			new TwigFilter('date_format', [DateFormatRuntime::class, 'get']),
			new TwigFilter('sec_format', [DateFormatRuntime::class, 'get_sec']),
			new TwigFilter('min_format', [DateFormatRuntime::class, 'get_min']),
			new TwigFilter('day_format', [DateFormatRuntime::class, 'get_day']),
			new TwigFilter('date_format_from_unix', [DateFormatRuntime::class, 'get_from_unix']),
		];
	}

	public function getFunctions():array
	{
		return [
			new TwigFunction('datepicker_format', [DateFormatRuntime::class, 'datepicker_format']),
			new TwigFunction('datepicker_placeholder', [DateFormatRuntime::class, 'datepicker_placeholder']),
			new TwigFunction('config', [ConfigRuntime::class, 'get']),
			new TwigFunction('s3_url', [S3UrlRuntime::class, 'get']),
			new TwigFunction('s3_link_open', [S3UrlRuntime::class, 'get_link_open']),
			new TwigFunction('context_url', [LinkUrlRuntime::class, 'context_url']),
			new TwigFunction('context_url_open', [LinkUrlRuntime::class, 'context_url_open']),
			new TwigFunction('system', [SystemRuntime::class, 'get']),
			new TwigFunction('account', [AccountRuntime::class, 'get']),
			new TwigFunction('user_fullname', [AccountRuntime::class, 'get_fullname']),
			new TwigFunction('username', [AccountRuntime::class, 'get_name']),
			new TwigFunction('account_code', [AccountRuntime::class, 'get_code']),
			new TwigFunction('account_balance', [AccountRuntime::class, 'get_balance']),
			new TwigFunction('mpp_ary', [MppAryRuntime::class, 'get'], ['needs_context' => true]),
			new TwigFunction('mpp_anon_ary', [MppAryRuntime::class, 'get_anon'], ['needs_context' => true]),
			new TwigFunction('mpp_guest_ary', [MppAryRuntime::class, 'get_guest'], ['needs_context' => true]),
			new TwigFunction('mpp_admin_ary', [MppAryRuntime::class, 'get_admin'], ['needs_context' => true]),
			new TwigFunction('assets', [AssetsRuntime::class, 'get']),
			new TwigFunction('assets_ary', [AssetsRuntime::class, 'get_ary']),
			new TwigFunction('heading', [HeadingRuntime::class, 'get_h1'], ['is_safe' => ['html']]),
			new TwigFunction('heading_sub', [HeadingRuntime::class, 'get_sub'], ['is_safe' => ['html']]),
			new TwigFunction('btn_top', [BtnTopRuntime::class, 'get'], ['is_safe' => ['html']]),
			new TwigFunction('btn_nav', [BtnNavRuntime::class, 'get'], ['is_safe' => ['html']]),
			new TwigFunction('pagination', [PaginationRuntime::class, 'get'], ['is_safe' => ['html']]),
			new TwigFunction('pp_role', [PpRoleRuntime::class, 'has_role']),
			new TwigFunction('pp_ary', [PpAryRuntime::class, 'get']),
			new TwigFunction('r_default', [RDefaultRuntime::class, 'get']),
			new TwigFunction('menu_sidebar', [MenuRuntime::class, 'get_sidebar']),
			new TwigFunction('menu_nav_admin', [MenuRuntime::class, 'get_nav_admin']),
			new TwigFunction('menu_nav_user', [MenuNavUserRuntime::class, 'get_nav_user']),
			new TwigFunction('menu_nav_logout', [MenuNavUserRuntime::class, 'get_nav_logout']),
			new TwigFunction('menu_nav_system', [MenuNavSystemRuntime::class, 'get_nav_system']),
			new TwigFunction('has_menu_nav_system', [MenuNavSystemRuntime::class, 'has_nav_system']),
			new TwigFunction('s_role', [SRoleRuntime::class, 'has_role']),
			new TwigFunction('get_s_id', [SRoleRuntime::class, 'get_s_id']),
			new TwigFunction('get_s_schema', [SRoleRuntime::class, 'get_s_schema']),
			new TwigFunction('is_s_master', [SRoleRuntime::class, 'is_s_master']),
			new TwigFunction('is_s_system_self', [SRoleRuntime::class, 'is_s_system_self']),
		];
	}

	public function underline(string $input, string $char = '-'):string
	{
		$len = strlen($input);
		return $input . "\r\n" . str_repeat($char, $len);
	}

	public function replace_when_zero(int $input, $replace = null):string
	{
		return $input === 0 ? $replace : $input;
	}
}
