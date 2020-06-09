<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\VarRouteService;
use Twig\Extension\RuntimeExtensionInterface;

class VarRouteRuntime implements RuntimeExtensionInterface
{
	protected VarRouteService $vr;

	public function __construct(
		VarRouteService $vr
	)
	{
		$this->vr = $vr;
	}

	public function get(string $menu_route):string
	{
		return $this->vr->get($menu_route);
	}
}
