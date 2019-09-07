<?php declare(strict_types=1);

namespace service;

use service\config;
use service\item_access;
use cnst\menu as cnst_menu;

class menu
{
	protected $config;
	protected $item_access;
	protected $tschema;
	protected $intersystem_en;
	protected $r_messages;
	protected $r_users;
	protected $r_news;
	protected $active_menu;

	public function __construct(
		config $config,
		item_access $item_access,
		string $tschema,
		bool $intersystem_en,
		string $r_messages,
		string $r_users,
		string $r_news
	)
	{
		$this->config = $config;
		$this->item_access = $item_access;
		$this->tschema = $tschema;
		$this->intersystem_en = $intersystem_en;
		$this->r_messages = $r_messages;
		$this->r_users = $r_users;
		$this->r_news = $r_news;
	}

	public function set(string $active_menu):void
	{
		$this->active_menu = $active_menu;
	}

	public function has_nav_menu(sring $menu):bool
	{

		return false;
	}

	public function nav_admin_menu(string $menu):array
	{
		$menu_ary = [];

		switch($menu)
		{
			case 'admin':
				$menu_ary = cnst_menu::NAV_ADMIN;

				if (!$this->intersystem_en)
				{
					unset($menu_ary['intersystems'], $menu_ary['apikeys'], $menu_ary['guest_mode']);
				}



				return $menu_ary;
				break;
		}

		return [];
	}

	public function get_sidebar():array
	{
		$menu_ary = [];

		foreach (cnst_menu::SIDEBAR as $m_route => $item)
		{
			if (!$this->item_access->is_visible($item['access']))
			{
				continue;
			}

			if (isset($item['config_en']))
			{
				if (!$this->config->get($item['config_en'], $this->tschema))
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
