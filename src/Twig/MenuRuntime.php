<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\MenuService;
use Twig\Extension\RuntimeExtensionInterface;

class MenuRuntime implements RuntimeExtensionInterface
{
	protected $menu_service;

	public function __construct(
		MenuService $menu_service
	)
	{
		$this->menu_service = $menu_service;
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
