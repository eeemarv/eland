<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\MenuService;
use Twig\Extension\RuntimeExtensionInterface;

class MenuRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected MenuService $menu_service
	)
	{
	}

	public function get_sidebar():array
	{
		return $this->menu_service->get_sidebar();
	}

	public function get_nav_admin():array
	{
		return $this->menu_service->get_nav_admin();
	}
}
