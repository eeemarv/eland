<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\MenuNavUserService;
use Twig\Extension\RuntimeExtensionInterface;

class MenuNavUserRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected MenuNavUserService $menu_nav_user_service
	)
	{
	}

	public function get_s_id():int
	{
		return $this->menu_nav_user_service->get_s_id();
	}

	public function get_nav_user():array
	{
		return $this->menu_nav_user_service->get_nav_user();
	}

	public function get_nav_logout():array
	{
		return $this->menu_nav_user_service->get_nav_logout();
	}
}
