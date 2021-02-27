<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\PageParamsService;
use Twig\Extension\RuntimeExtensionInterface;

class PpAryRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected PageParamsService $pp
	)
	{
	}

	public function get():array
	{
		return $this->pp->ary();
	}
}
