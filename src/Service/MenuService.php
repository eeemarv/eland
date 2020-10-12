<?php declare(strict_types=1);

namespace App\Service;

use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Cnst\MenuCnst;
use App\Render\BtnNavRender;

class MenuService
{
	protected ConfigService $config_service;
	protected ItemAccessService $item_access_service;
	protected BtnNavRender $btn_nav_render;
	protected PageParamsService $pp;
	protected VarRouteService $vr;
	protected string $active_menu;

	public function __construct(
		ConfigService $config_service,
		ItemAccessService $item_access_service,
		BtnNavRender $btn_nav_render,
		PageParamsService $pp,
		VarRouteService $vr
	)
	{
		$this->config_service = $config_service;
		$this->item_access_service = $item_access_service;
		$this->btn_nav_render = $btn_nav_render;
		$this->pp = $pp;
		$this->vr = $vr;
	}

	public function set(string $active_menu):void
	{
		if ($this->pp->is_admin())
		{
			$disabled_items = [];

			if (!$this->config_service->get_intersystem_en($this->pp->schema()))
			{
				$disabled_items['intersystems'] = true;
			}

			$this->btn_nav_render->local_admin($active_menu, $this->pp);

			if (isset(MenuCnst::LOCAL_ADMIN_MAIN[$active_menu]))
			{
				$this->active_menu = MenuCnst::LOCAL_ADMIN_MAIN[$active_menu];
				return;
			}
		}

		$this->active_menu = $active_menu;
	}

	public function get_active():string
	{
		return $this->active_menu;
	}

	public function get_nav_admin():array
	{
		$m_ary = [];
		$system = $this->pp->system();

		foreach (MenuCnst::NAV_ADMIN as $key => $def)
		{
			$m_ary[$key] = $def;
			$m_ary[$key]['system'] = $system;

			if (isset($def['fallback_route']))
			{
				$m_ary[$key]['route'] = $this->vr->get_inter($this->active_menu, $this->pp->schema());
			}
		}

		if (!$this->config_service->get_intersystem_en($this->pp->schema()))
		{
			unset($m_ary['guest_mode']);
		}

		if (isset($m_ary[$this->active_menu]))
		{
			$m_ary[$this->active_menu]['active'] = true;
		}

		$m_ary[$this->pp->role() . '_mode']['active_group'] = true;

		if ($this->pp->edit_enabled())
		{
			$m_ary['edit_mode']['active_group'] = true;
			$m_ary['edit_mode']['params']['edit'] = '';
		}

		return $m_ary;
	}

	public function get_sidebar():array
	{
		$menu_ary = [];

		foreach (MenuCnst::SIDEBAR as $menu_route => $item)
		{
			if (!$this->item_access_service->is_visible($item['access']))
			{
				continue;
			}

			if (isset($item['config_en']))
			{
				if (!$this->config_service->get_bool($item['config_en'], $this->pp->schema()))
				{
					continue;
				}
			}

			$menu_ary[] = [
				'route'			=> $this->vr->get($menu_route),
				'label'			=> $item['label'],
				'fa'			=> $item['fa'],
				'active'		=> $menu_route === $this->active_menu,
				'config_only'	=> $this->pp->is_admin() && $item['access'] === 'anonymous',
			];
		}

		return $menu_ary;
	}
}
