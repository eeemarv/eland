<?php declare(strict_types=1);

namespace twig;

use Symfony\Component\HttpFoundation\Request;

use cnst\menu as cnst_menu;

class menu
{
	protected $pp_anonymous;
	protected $pp_guest;
	protected $pp_user;
	protected $pp_admin;
	protected $s_master;
	protected $s_elas_guest;
	protected $request;

	public function __construct(
		bool $pp_anonymous,
		bool $pp_guest,
		bool $pp_user,
		bool $pp_admin,
		bool $s_master,
		bool $s_elas_guest,
		Request $request
	)
	{
		$this->pp_anonymous = $pp_anonymous;
		$this->pp_guest = $pp_guest;
		$this->pp_user = $pp_user;
		$this->pp_admin = $pp_admin;
		$this->s_master = $s_master;
		$this->s_elas_guest = $s_elas_guest;
		$this->request = $request;
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

	public function menu():array
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
				'route'		=> isset($item['var_route']) ? $this->{$item['var_route']} : $m_route,
				'label'		=> $item['label'],
				'fa'		=> $item['fa'],
				'active'	=> $route = $this->menu,
			];
		}

		return $menu_ary;
	}

	private function anonymous():array
	{

	}
}
