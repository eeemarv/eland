<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PpRoleExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('pp_role', [PpRoleRuntime::class, 'has_role']),
		];
	}
}
