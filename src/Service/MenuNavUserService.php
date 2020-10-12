<?php declare(strict_types=1);

namespace App\Service;

use App\Cnst\MenuCnst;

class MenuNavUserService
{
	protected ConfigService $config_service;
	protected SessionUserService $su;
	protected VarRouteService $vr;

	public function __construct(
		ConfigService $config_service,
		SessionUserService $su,
		VarRouteService $vr
	)
	{
		$this->config_service = $config_service;
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

		foreach ($m_ary as $route => $data)
		{
			if (isset($data['config_en']))
			{
				if (!$this->config_service->get_bool($data['config_en'], $this->su->schema()))
				{
					continue;
				}
			}

			$data['params'] = [];

			if (isset($data['params_id']))
			{
				$data['params']['id'] = $this->su->id();
			}
			else if (isset($data['params_filter_uid']))
			{
				$data['params']['f']['uid'] = $this->su->id();
			}

			$data['route'] = $this->vr->get($route);

			$nav_user_menu[$route] = $data;
		}

		$nav_user_menu += MenuCnst::NAV_LOGOUT;

		return $nav_user_menu;
	}

	public function get_nav_logout():array
	{
		return MenuCnst::NAV_LOGOUT;
	}
}
