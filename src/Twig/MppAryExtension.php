<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MppAryExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('mpp_ary', [MppAryRuntime::class, 'get'], ['needs_context' => true]),
			new TwigFunction('mpp_anon_ary', [MppAryRuntime::class, 'get_anon'], ['needs_context' => true]),
			new TwigFunction('mpp_guest_ary', [MppAryRuntime::class, 'get_guest'], ['needs_context' => true]),
			new TwigFunction('mpp_admin_ary', [MppAryRuntime::class, 'get_admin'], ['needs_context' => true]),
		];
	}
}
