<?php declare(strict_types=1);

namespace service;

use service\intersystems;
use service\systems;
use service\menu;
use service\config;
use service\user_cache;
use cnst\menu as cnst_menu;

class menu_nav_system
{
	protected $intersystems;
	protected $systems;
	protected $s_logins;
	protected $s_schema;
	protected $pp_schema;
	protected $intersystem_en;
	protected $menu;
	protected $config;
	protected $user_cache;

	public function __construct(
		intersystems $intersystems,
		systems $systems,
		array $s_logins,
		string $s_schema,
		string $pp_schema,
		bool $intersystem_en,
		menu $menu,
		config $config,
		user_cache $user_cache
	)
	{
		$this->intersystems = $intersystems;
		$this->systems = $systems;
		$this->s_logins = $s_logins;
		$this->s_schema = $s_schema;
		$this->pp_schema = $pp_schema;
		$this->intersystem_en = $intersystem_en;
		$this->menu = $menu;
		$this->config = $config;
		$this->user_cache = $user_cache;
	}

	public function has_nav_system():bool
	{
		return ($this->intersystems->get_count($this->s_schema)
			+ count($this->s_logins)) > 1;
	}

	public function get_nav_system():array
	{
		$m_ary = [];

		$m_ary[] = [
			'header'	=> true,
			'label'		=> count($this->s_logins) > 1 ? 'Eigen Systemen' : 'Eigen Systeem',
		];

		$route = $this->menu->get_fallback_route();

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
					'system' 		=> $this->systems->get_system($login_schema),
					'role_short'	=> $role_short,
				],
				'label'		=> $this->config->get('systemname', $login_schema),
			];

			if ($login_schema === $this->s_schema)
			{
				if ($login_schema === $this->pp_schema)
				{
					$m_item['active'] = true;
				}
				else if (count($this->s_logins) > 1)
				{
					$m_item['active_group'] = true;
				}
			}

			$m_ary[] = $m_item;
		}

		if (!$this->intersystems->get_count($this->s_schema))
		{
			return $m_ary;
		}

		$m_ary[] = [
			'header'	=> true,
			'label'		=> $this->intersystems->get_count($this->s_schema) > 1 ? 'Gekoppelde interSystemen' : 'Gekoppeld interSysteem',
		];

		foreach ($this->intersystems->get_eland($this->s_schema) as $eland_schema => $h)
		{
			$m_item = [
				'route'		=> $route,
				'params'	=> [
					'system' 		=> $this->systems->get_system($eland_schema),
					'role_short'	=> 'g',
					'welcome'		=> '1',
				],
				'label'		=> $this->config->get('systemname', $eland_schema),
			];

			if ($this->pp_schema === $eland_schema)
			{
				$m_item['active'] = true;
			}

			$m_ary[] = $m_item;
		}

		foreach ($this->intersystems->get_elas($this->s_schema) as $elas_group_id => $elas_group)
		{
			$m_ary[] = [
				'label'			=> $elas_group['groupname'],
				'elas_group_id'	=> $elas_group_id,
			];
		}

		return $m_ary;
	}
}