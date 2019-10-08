<?php declare(strict_types=1);

namespace twig;

use service\menu as service_menu;

class menu
{
	protected $service_menu;

	public function __construct(
		service_menu $service_menu
	)
	{
		$this->service_menu = $service_menu;
	}

	public function get_sidebar():array
	{
		return $this->service_menu->get_sidebar();
	}

	public function get_nav_admin():array
	{
		return $this->service_menu->get_nav_admin();
	}

	public function get_nav_user():array
	{
		return $this->service_menu_nav_user->get_nav_user();
	}

	public function get_nav_logout():array
	{
		return $this->service_menu_nav_user->get_nav_logout();
	}

	public function get_nav_system():array
	{
		return $this->service_menu_nav_system->get_nav_system();
	}
}
