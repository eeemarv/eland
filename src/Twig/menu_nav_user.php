<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\menu_nav_user as service_menu_nav_user;

class menu_nav_user
{
	protected $service_menu_nav_user;

	public function __construct(
		service_menu_nav_user $service_menu_nav_user
	)
	{
		$this->service_menu_nav_user = $service_menu_nav_user;
	}

	public function get_s_id():int
	{
		return $this->service_menu_nav_user->get_s_id();
	}

	public function get_nav_user():array
	{
		return $this->service_menu_nav_user->get_nav_user();
	}

	public function get_nav_logout():array
	{
		return $this->service_menu_nav_user->get_nav_logout();
	}
}
