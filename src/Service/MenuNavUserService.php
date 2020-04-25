<?php declare(strict_types=1);

namespace App\Service;

use App\Cnst\MenuCnst;

class MenuNavUserService
{
	protected SessionUserService $su;
	protected VarRouteService $vr;

	public function __construct(
		SessionUserService $su,
		VarRouteService $vr
	)
	{
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

		$m_ary['users_show']['params']['id'] = $this->su->id();
		$m_ary['users_show']['route'] = $this->vr->get('users_show');
		$m_ary['messages']['params']['f']['uid'] = $this->su->id();
		$m_ary['messages']['route'] = $this->vr->get('messages');
		$m_ary['transactions']['params']['f']['uid'] = $this->su->id();

		$m_ary += MenuCnst::NAV_LOGOUT;

		return $m_ary;
	}

	public function get_nav_logout():array
	{
		return MenuCnst::NAV_LOGOUT;
	}
}
