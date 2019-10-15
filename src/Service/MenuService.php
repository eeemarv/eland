<?php declare(strict_types=1);

namespace App\Service;

use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Cnst\MenuCnst;

class MenuService
{
	const FALLBACK_VAR = [
		'messages'		=> true,
		'users'			=> true,
		'news'			=> true,
		'transactions'	=> true,
	];

	protected $config_service;
	protected $item_access_service;
	protected $pp;
	protected $vr;
	protected $active_menu;

	public function __construct(
		ConfigService $config_service,
		ItemAccessService $item_access_service,
		PageParamsService $pp,
		VarRouteService $vr
	)
	{
		$this->config_service = $config_service;
		$this->item_access_service = $item_access_service;
		$this->pp = $pp;
		$this->vr = $vr;
	}

	public function set(string $active_menu):void
	{
		$this->active_menu = $active_menu;
	}

	public function get_fallback_route():string
	{
		if (isset(self::FALLBACK_VAR[$this->active_menu]))
		{
			$route = $this->active_menu;
		}
		else
		{
			$route = 'default';
		}

		return str_replace('_admin', '', $this->vr->get($route));
	}

	public function get_nav_admin():array
	{
		$m_ary = MenuCnst::NAV_ADMIN;

		$m_ary['user_mode']['params']['system'] = $this->pp->system();
		$m_ary['guest_mode']['params']['system'] = $this->pp->system();

		$fallback_route = $this->get_fallback_route();

		$m_ary['user_mode']['route'] = $fallback_route;
		$m_ary['guest_mode']['route'] = $fallback_route;

		if (!$this->config_service->get_intersystem_en($this->pp->schema()))
		{
			unset($m_ary['intersystems'], $m_ary['apikeys'], $m_ary['guest_mode']);
		}

		if (isset($m_ary[$this->active_menu]))
		{
			$m_ary[$this->active_menu]['active'] = true;
		}

		return $m_ary;
	}

	public function get_sidebar():array
	{
		$menu_ary = [];

		foreach (MenuCnst::SIDEBAR as $m_route => $item)
		{
			if (!$this->item_access_service->is_visible($item['access']))
			{
				continue;
			}

			if (isset($item['config_en']))
			{
				if (!$this->config_service->get($item['config_en'], $this->pp->schema()))
				{
					continue;
				}
			}

			$menu_ary[] = [
				'route'			=> $this->vr->get($m_route),
				'label'			=> $item['label'],
				'fa'			=> $item['fa'],
				'active'		=> $m_route === $this->active_menu,
			];
		}

		return $menu_ary;
	}
}
