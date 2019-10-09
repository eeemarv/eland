<?php declare(strict_types=1);

namespace App\Service;

use App\Cnst\MenuCnst;

class MenuNavUser
{
	protected $s_id;
	protected $r_messages;
	protected $r_users_show;

	public function __construct(
		int $s_id,
		string $r_messages,
		string $r_users_show
	)
	{
		$this->s_id = $s_id;
		$this->r_messages = $r_messages;
		$this->r_users_show = $r_users_show;
	}

	public function get_s_id():int
	{
		return $this->s_id;
	}

	public function get_nav_user():array
	{
		$m_ary = MenuCnst::NAV_USER;

		$m_ary['users_show']['params']['id'] = $this->s_id;
		$m_ary['users_show']['route'] = $this->r_users_show;
		$m_ary['messages']['params']['f']['uid'] = $this->s_id;
		$m_ary['messages']['route'] = $this->r_messages;
		$m_ary['transactions']['params']['f']['uid'] = $this->s_id;

		$m_ary += MenuCnst::NAV_LOGOUT;

		return $m_ary;
	}

	public function get_nav_logout():array
	{
		return MenuCnst::NAV_LOGOUT;
	}
}
