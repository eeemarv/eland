<?php declare(strict_types=1);

namespace twig;

use service\menu as service_menu;

class menu
{
	protected $service_menu;

	public function __construct(
		service_menu $service_menu
	)
	{
		$this->service_menu = $service_menu;
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
		return $this->service_menu->get_sidebar();
	}

	public function get_nav_admin():array
	{
		return $this->service_menu->get_nav_admin();
	}
}
