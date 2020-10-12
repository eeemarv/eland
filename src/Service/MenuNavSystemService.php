<?php declare(strict_types=1);

namespace App\Service;

use App\Cnst\RoleCnst;
use App\Service\IntersystemsService;
use App\Service\SystemsService;
use App\Service\MenuService;
use App\Service\ConfigService;
use App\Service\UserCacheService;

class MenuNavSystemService
{
	protected IntersystemsService $intersystems_service;
	protected SystemsService $systems_service;
	protected MenuService $menu_service;
	protected ConfigService $config_service;
	protected UserCacheService $user_cache_service;
	protected PageParamsService $pp;
	protected VarRouteService $vr;
	protected SessionUserService $su;

	public function __construct(
		IntersystemsService $intersystems_service,
		SystemsService $systems_service,
		MenuService $menu_service,
		ConfigService $config_service,
		UserCacheService $user_cache_service,
		PageParamsService $pp,
		VarRouteService $vr,
		SessionUserService $su
	)
	{
		$this->intersystems_service = $intersystems_service;
		$this->systems_service = $systems_service;
		$this->menu_service = $menu_service;
		$this->config_service = $config_service;
		$this->user_cache_service = $user_cache_service;
		$this->pp = $pp;
		$this->vr = $vr;
		$this->su = $su;
	}

	public function has_nav_system():bool
	{
		if ($this->su->is_guest())
		{
			$count_intersystems = 0;
		}
		else
		{
			$count_intersystems = $this->intersystems_service->get_count($this->su->schema());
		}

		return ($count_intersystems + count($this->su->logins())) > 1;
	}

	public function get_nav_system():array
	{
		$m_ary = [];

		$m_ary[] = [
			'header'	=> true,
			'label'		=> count($this->su->logins()) > 1 ? 'Eigen Systemen' : 'Eigen Systeem',
		];

		$active_menu_route = $this->menu_service->get_active();

		foreach ($this->su->logins() as $login_schema => $login_id)
		{
			if ($login_id === 'master')
			{
				$role_short = 'a';
			}
			else
			{
				$role = $this->user_cache_service->get_role($login_id, $login_schema);

				if (!isset(RoleCnst::SHORT[$role]))
				{
					continue;
				}

				$role_short = RoleCnst::SHORT[$role];
			}

			$m_item = [
				'route'		=> $this->vr->get_inter($active_menu_route, $login_schema),
				'params'	=> [
					'system' 		=> $this->systems_service->get_system($login_schema),
					'role_short'	=> $role_short,
					'os'			=> '',
				],
				'label'		=> $this->config_service->get_str('system.name', $login_schema),
			];

			if ($login_schema === $this->su->schema())
			{
				if ($login_schema === $this->pp->schema())
				{
					$m_item['active'] = true;
				}
				else
				{
					$m_item['active_group'] = true;
				}
			}

			$m_ary[] = $m_item;
		}

		if ($this->su->is_guest())
		{
			return $m_ary;
		}

		if (!$this->intersystems_service->get_count($this->pp->schema()))
		{
			return $m_ary;
		}

		if (!$this->config_service->get_intersystem_en($this->pp->schema()))
		{
			return $m_ary;
		}

		$m_ary[] = [
			'header'	=> true,
			'label'		=> $this->intersystems_service->get_count($this->su->schema()) > 1 ? 'Gekoppelde interSystemen' : 'Gekoppeld interSysteem',
		];

		$eland_intersystems = $this->intersystems_service->get_eland($this->su->schema());

		foreach ($eland_intersystems as $eland_schema => $h)
		{
			$m_item = [
				'route'		=> $this->vr->get_inter($active_menu_route, $eland_schema),
				'params'	=> [
					'os'			=> $this->systems_service->get_system($this->su->schema()),
					'system' 		=> $this->systems_service->get_system($eland_schema),
					'role_short'	=> 'g',
					'welcome'		=> '1',
				],
				'label'		=> $this->config_service->get_str('system.name', $eland_schema),
			];

			if ($this->pp->schema() === $eland_schema)
			{
				$m_item['active'] = true;
			}

			$m_ary[] = $m_item;
		}

		return $m_ary;
	}
}
