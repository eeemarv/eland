<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\VarRouteService;
use Twig\Attribute\AsTwigFunction;

class RDefaultExtension
{
	public function __construct(
		private readonly VarRouteService $vr
	)
	{
	}

  #[AsTwigFunction(name: 'r_default')]
	public function get():string
	{
		return $this->vr->get('default');
	}
}
