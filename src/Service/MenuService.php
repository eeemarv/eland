<?php declare(strict_types=1);

namespace App\Service;

use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Cnst\MenuCnst;

class MenuService
{
	protected string $active_menu;

	public function __construct(
		protected ConfigService $config_service,
		protected ItemAccessService $item_access_service,
		protected PageParamsService $pp,
		protected VarRouteService $vr
	)
	{
	}

	public function set(string $active_menu):void
	{
		if ($this->pp->is_admin())
		{
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
}
