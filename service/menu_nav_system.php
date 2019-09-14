<?php declare(strict_types=1);

namespace service;

use service\intersystems;
use cnst\menu as cnst_menu;

class menu_nav_system
{
	protected $intersystems;
	protected $s_logins;
	protected $s_schema;
	protected $pp_schema;
	protected $intersystem_en;
	protected $r_messages;
	protected $r_users_show;

	public function __construct(
		intersystems $intersystems,
		array $s_logins,
		string $s_schema,
		string $pp_schema,
		bool $intersystem_en,
		string $r_messages,
		string $r_users_show
	)
	{
		$this->intersystems = $intersystems;
		$this->s_logins = $s_logins;
		$this->s_schema = $s_schema;
		$this->pp_schema = $pp_schema;
		$this->intersystem_en = $intersystem_en;
		$this->r_messages = $r_messages;
		$this->r_users_show = $r_users_show;
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

		foreach ($this->s_logins as $login_schema => $login_id)
		{
			if ($login_schema === $this->s_schema)
			{
				if ($login_schema === $this->pp_schema)
				{
					$out .= ' class="active"';
				}
				else if (count($this->s_logins) > 1)
				{
					$out .= ' class="active-group"';
				}
			}
		}

		return $m_ary;
	}

	public function get_route_from_menu(string $menu):string
	{
		if (isset(cnst_menu::SIDEBAR[$menu]['var_route']))
		{
			$var_route = cnst_menu::SIDEBAR[$menu]['var_route'];
			return $this->{$var_route};
		}

		return $menu;
	}
}
