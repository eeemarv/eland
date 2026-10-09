<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\VarRouteService;
use Twig\Attribute\AsTwigFilter;

class VarRouteExtension
{
	public function __construct(
		private readonly VarRouteService $vr
	)
	{
	}
  #[AsTwigFilter(name: 'var_route')]
	public function get(string $menu_route):string
	{
		return $this->vr->get($menu_route);
	}

  #[AsTwigFilter(name: 'fallback_route')]
	public function get_fallback(string $active_menu, string $schema):string
	{
		return $this->vr->get_fallback_route($active_menu, $schema);
	}
}
