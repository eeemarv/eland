<?php declare(strict_types=1);

namespace App\Service;

use App\Service\IntersystemsService;
use App\Service\SystemsService;
use App\Service\MenuService;
use App\Service\ConfigService;
use App\Service\UserCacheService;

class MenuNavSystemService
{
	protected $intersystems_service;
	protected $systems_service;
	protected $menu_service;
	protected $config_service;
	protected $user_cache_service;
	protected $pp;
	protected $su;

	public function __construct(
		IntersystemsService $intersystems_service,
		SystemsService $systems_service,
		MenuService $menu_service,
		ConfigService $config_service,
		UserCacheService $user_cache_service,
		PageParamsService $pp,
		SessionUserService $su
	)
	{
		$this->intersystems_service = $intersystems_service;
		$this->systems_service = $systems_service;
		$this->menu_service = $menu_service;
		$this->config_service = $config_service;
		$this->user_cache_service = $user_cache_service;
		$this->pp = $pp;
		$this->su = $su;
	}

	public function has_nav_system():bool
	{
		if ($this->su->is_elas_guest())
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

		$route = $this->menu_service->get_fallback_route();

		foreach ($this->su->logins() as $login_schema => $login_id)
		{
			if ($login_id === 'elas')
			{
				$role_short = 'g';
			}
			else if ($login_id === 'master')
			{
				$role_short = 'a';
			}
			else
			{
				$role = $this->user_cache_service->get_role($login_id, $login_schema);

				if ($role === 'user')
				{
					$role_short = 'u';
				}
				else if ($role === 'admin')
				{
					$role_short = 'a';
				}
				else
				{
					continue;
				}
			}

			$m_item = [
				'route'		=> $route,
				'params'	=> [
					'system' 		=> $this->systems_service->get_system($login_schema),
					'role_short'	=> $role_short,
					'org_system'	=> '',
				],
				'label'		=> $this->config_service->get('systemname', $login_schema),
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

		if ($this->su->is_elas_guest())
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

		foreach ($this->intersystems_service->get_eland($this->su->schema()) as $eland_schema => $h)
		{
			$m_item = [
				'route'		=> $route,
				'params'	=> [
					'org_system'	=> $this->systems_service->get_system($this->su->schema()),
					'system' 		=> $this->systems_service->get_system($eland_schema),
					'role_short'	=> 'g',
					'welcome'		=> '1',
				],
				'label'		=> $this->config_service->get('systemname', $eland_schema),
			];

			if ($this->pp->schema() === $eland_schema)
			{
				$m_item['active'] = true;
			}

			$m_ary[] = $m_item;
		}

		foreach ($this->intersystems_service->get_elas($this->su->schema()) as $elas_group_id => $elas_group)
		{
			$m_ary[] = [
				'label'			=> $elas_group['groupname'],
				'elas_group_id'	=> $elas_group_id,
			];
		}

		return $m_ary;
	}
}
