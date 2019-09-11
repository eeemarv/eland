<?php declare(strict_types=1);

namespace service;

use cnst\menu as cnst_menu;

class menu_nav_system
{
	protected $intersystem_en;
	protected $r_messages;
	protected $r_users_show;

	public function __construct(
		bool $intersystem_en,
		string $r_messages,
		string $r_users_show
	)
	{
		$this->intersystem_en = $intersystem_en;
		$this->r_messages = $r_messages;
		$this->r_users_show = $r_users_show;
	}

	public function get_intersystem_en():bool
	{
		return $this->intersystem_en;
	}

	public function get_s_id():int
	{
		return $this->s_id;
	}

	public function get_nav_user():array
	{
		$m_ary = cnst_menu::NAV_USER;

		$m_ary['users_show']['params']['id'] = $this->s_id;
		$m_ary['users_show']['route'] = $this->r_users_show;
		$m_ary['messages']['params']['f']['uid'] = $this->s_id;
		$m_ary['messages']['route'] = $this->r_messages;
		$m_ary['transactions']['params']['f']['uid'] = $this->s_id;

		$m_ary += cnst_menu::NAV_LOGOUT;

		return $m_ary;
	}

	public function get_nav_logout():array
	{
		return cnst_menu::NAV_LOGOUT;
	}
}
