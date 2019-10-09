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
	protected $s_logins;
	protected $s_schema;
	protected $pp_schema;
	protected $intersystem_en;
	protected $menu_service;
	protected $config_service;
	protected $user_cache_service;
	protected $s_elas_guest;

	public function __construct(
		IntersystemsService $intersystems_service,
		SystemsService $systems_service,
		array $s_logins,
		string $s_schema,
		string $pp_schema,
		bool $intersystem_en,
		MenuService $menu_service,
		ConfigService $config_service,
		UserCacheService $user_cache_service,
		bool $s_elas_guest
	)
	{
		$this->intersystems_service = $intersystems_service;
		$this->systems_service = $systems_service;
		$this->s_logins = $s_logins;
		$this->s_schema = $s_schema;
		$this->pp_schema = $pp_schema;
		$this->intersystem_en = $intersystem_en;
		$this->menu_service = $menu_service;
		$this->config_service = $config_service;
		$this->user_cache_service = $user_cache_service;
		$this->s_elas_guest = $s_elas_guest;
	}

	public function has_nav_system():bool
	{
		if ($this->s_elas_guest)
		{
			$count_intersystems = 0;
		}
		else
		{
			$count_intersystems = $this->intersystems_service->get_count($this->s_schema);
		}

		return ($count_intersystems + count($this->s_logins)) > 1;
	}

	public function get_nav_system():array
	{
		$m_ary = [];

		$m_ary[] = [
			'header'	=> true,
			'label'		=> count($this->s_logins) > 1 ? 'Eigen Systemen' : 'Eigen Systeem',
		];

		$route = $this->menu_service->get_fallback_route();

		foreach ($this->s_logins as $login_schema => $login_id)
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
				$role = $this->user_cache->get_role($login_id, $login_schema);

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

			if ($login_schema === $this->s_schema)
			{
				if ($login_schema === $this->pp_schema)
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

		if ($this->s_elas_guest)
		{
			return $m_ary;
		}

		if (!$this->intersystems_service->get_count($this->s_schema))
		{
			return $m_ary;
		}

		if (!$this->intersystem_en)
		{
			return $m_ary;
		}

		$m_ary[] = [
			'header'	=> true,
			'label'		=> $this->intersystems_service->get_count($this->s_schema) > 1 ? 'Gekoppelde interSystemen' : 'Gekoppeld interSysteem',
		];

		foreach ($this->intersystems_service->get_eland($this->s_schema) as $eland_schema => $h)
		{
			$m_item = [
				'route'		=> $route,
				'params'	=> [
					'org_system'	=> $this->systems_service->get_system($this->s_schema),
					'system' 		=> $this->systems_service->get_system($eland_schema),
					'role_short'	=> 'g',
					'welcome'		=> '1',
				],
				'label'		=> $this->config_service->get('systemname', $eland_schema),
			];

			if ($this->pp_schema === $eland_schema)
			{
				$m_item['active'] = true;
			}

			$m_ary[] = $m_item;
		}

		foreach ($this->intersystems_service->get_elas($this->s_schema) as $elas_group_id => $elas_group)
		{
			$m_ary[] = [
				'label'			=> $elas_group['groupname'],
				'elas_group_id'	=> $elas_group_id,
			];
		}

		return $m_ary;
	}
}
