<?php declare(strict_types=1);

namespace App\Service;

use App\Cnst\MenuCnst;

class MenuNavUserService
{
	protected ConfigService $config_service;
	protected PageParamsService $pp;
	protected SessionUserService $su;
	protected VarRouteService $vr;

	public function __construct(
		ConfigService $config_service,
		PageParamsService $pp,
		SessionUserService $su,
		VarRouteService $vr
	)
	{
		$this->config_service = $config_service;
		$this->pp = $pp;
		$this->su = $su;
		$this->vr = $vr;
	}

	public function get_s_id():int
	{
		return $this->su->id();
	}

	public function get_nav_user():array
	{
		$m_ary = MenuCnst::NAV_USER;

		$nav_user_menu = [];

		foreach ($m_ary as $menu_key => $data)
		{
			if (isset($data['config_en']))
			{
				if (!$this->config_service->get_bool($data['config_en'], $this->su->schema()))
				{
					continue;
				}
			}

			if (isset($data['var_route']))
			{
				$data['route'] = $this->vr->get($data['var_route']);
			}

			if (isset($data['route']) && $data['route'] === $this->pp->route())
			{
				$data['active'] = true;
			}

			$nav_user_menu[$menu_key] = $data;
		}

		return $nav_user_menu;
	}

	public function get_nav_logout():array
	{
		return MenuCnst::NAV_USER['logout'];
	}
}
