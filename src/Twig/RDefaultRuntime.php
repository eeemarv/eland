<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\VarRouteService;
use Twig\Extension\RuntimeExtensionInterface;

class RDefaultRuntime implements RuntimeExtensionInterface
{
	protected $vr;

	public function __construct(
		VarRouteService $vr
	)
	{
		$this->vr = $vr;
	}

	public function get():string
	{
		return $this->vr->get('default');
	}
}
