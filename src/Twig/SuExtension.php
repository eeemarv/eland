<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SuExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('su_role', [SuRuntime::class, 'su_role']),
			new TwigFunction('su_ary', [SuRuntime::class, 'su_ary']),
			new TwigFunction('su_id', [SuRuntime::class, 'su_id']),
			new TwigFunction('su_schema', [SuRuntime::class, 'su_schema']),
			new TwigFunction('su_is_master', [SuRuntime::class, 'su_is_master']),
			new TwigFunction('su_is_owner', [SuRuntime::class, 'su_is_owner']),
			new TwigFunction('su_is_system_self', [SuRuntime::class, 'su_is_system_self']),
			new TwigFunction('su_logins_role_short', [SuRuntime::class, 'su_logins_role_short']),
		];
	}
}
