<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\MenuNavSystemService;
use Twig\Extension\RuntimeExtensionInterface;

class MenuNavSystemRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected MenuNavSystemService $menu_nav_system_service
	)
	{
	}

	public function has_nav_system():bool
	{
		return $this->menu_nav_system_service->has_nav_system();
	}

	public function get_nav_system():array
	{
		return $this->menu_nav_system_service->get_nav_system();
	}
}
