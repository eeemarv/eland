<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PpExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('pp_ary', [PpRuntime::class, 'get_ary']),
			new TwigFunction('pp_schema', [PpRuntime::class, 'get_schema']),
			new TwigFunction('pp_role', [PpRuntime::class, 'has_role']),
		];
	}
}
