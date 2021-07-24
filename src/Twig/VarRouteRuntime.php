<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\VarRouteService;
use Twig\Extension\RuntimeExtensionInterface;

class VarRouteRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected VarRouteService $vr
	)
	{
	}

	public function get(string $menu_route):string
	{
		return $this->vr->get($menu_route);
	}

	public function get_fallback(string $active_menu, string $schema):string
	{
		return $this->vr->get_fallback_route($active_menu, $schema);
	}
}
