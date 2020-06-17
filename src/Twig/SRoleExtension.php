<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SRoleExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('s_role', [SRoleRuntime::class, 'has_role']),
			new TwigFunction('get_s_id', [SRoleRuntime::class, 'get_s_id']),
			new TwigFunction('s_is_owner', [SRoleRuntime::class, 's_is_owner']),
			new TwigFunction('get_s_schema', [SRoleRuntime::class, 'get_s_schema']),
			new TwigFunction('is_s_master', [SRoleRuntime::class, 'is_s_master']),
			new TwigFunction('is_s_system_self', [SRoleRuntime::class, 'is_s_system_self']),
		];
	}
}
