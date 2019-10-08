<?php declare(strict_types=1);

namespace App\Twig;

use service\menu_nav_system as service_menu_nav_system;

class menu_nav_system
{
	protected $service_menu_nav_system;

	public function __construct(
		service_menu_nav_system $service_menu_nav_system
	)
	{
		$this->service_menu_nav_system = $service_menu_nav_system;
	}

	public function has_nav_system():bool
	{
		return $this->service_menu_nav_system->has_nav_system();
	}

	public function get_nav_system():array
	{
		return $this->service_menu_nav_system->get_nav_system();
	}
}
