<?php declare(strict_types=1);

namespace App\Service;

use App\Service\Config;
use App\Service\ItemAccess;
use App\Cnst\MenuCnst;

class Menu
{
	const FALLBACK_VAR = [
		'messages'		=> true,
		'users'			=> true,
		'news'			=> true,
		'transactions'	=> false,
	];

	protected $config;
	protected $item_access;
	protected $pp_schema;
	protected $pp_system;
	protected $intersystem_en;
	protected $r_messages;
	protected $r_users;
	protected $r_news;
	protected $r_default;
	protected $active_menu;

	public function __construct(
		Config $config,
		ItemAccess $item_access,
		string $pp_schema,
		string $pp_system,
		bool $intersystem_en,
		string $r_messages,
		string $r_users,
		string $r_news,
		string $r_default
	)
	{
		$this->config = $config;
		$this->item_access = $item_access;
		$this->pp_schema = $pp_schema;
		$this->pp_system = $pp_system;
		$this->intersystem_en = $intersystem_en;
		$this->r_messages = $r_messages;
		$this->r_users = $r_users;
		$this->r_news = $r_news;
		$this->r_default = $r_default;
	}

	public function set(string $active_menu):void
	{
		$this->active_menu = $active_menu;
	}

	public function get_fallback_route():string
	{
		if (isset(self::FALLBACK_VAR[$this->active_menu]))
		{
			if (self::FALLBACK_VAR[$this->active_menu])
			{
				$route = 'r_' . $this->active_menu;
				$route = $this->{$route};
				return str_replace('_admin', '', $route);
			}

			return $this->active_menu;
		}

		return str_replace('_admin', '', $this->r_default);
	}

	public function get_nav_admin():array
	{
		$m_ary = MenuCnst::NAV_ADMIN;

		$m_ary['user_mode']['params']['system'] = $this->pp_system;
		$m_ary['guest_mode']['params']['system'] = $this->pp_system;

		$fallback_route = $this->get_fallback_route();

		$m_ary['user_mode']['route'] = $fallback_route;
		$m_ary['guest_mode']['route'] = $fallback_route;

		if (!$this->intersystem_en)
		{
			unset($m_ary['intersystems'], $m_ary['apikeys'], $m_ary['guest_mode']);
		}

		if (isset($m_ary[$this->active_menu]))
		{
			$m_ary[$this->active_menu]['active'] = true;
		}

		return $m_ary;
	}

	public function get_sidebar():array
	{
		$menu_ary = [];

		foreach (MenuCnst::SIDEBAR as $m_route => $item)
		{
			if (!$this->item_access->is_visible($item['access']))
			{
				continue;
			}

			if (isset($item['config_en']))
			{
				if (!$this->config->get($item['config_en'], $this->pp_schema))
				{
					continue;
				}
			}

			$menu_ary[] = [
				'route'			=> isset($item['var_route']) ? $this->{$item['var_route']} : $m_route,
				'label'			=> $item['label'],
				'fa'			=> $item['fa'],
				'active'		=> $m_route === $this->active_menu,
			];
		}

		return $menu_ary;
	}
}
