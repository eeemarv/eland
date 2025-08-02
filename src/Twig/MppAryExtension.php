<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Page parameter functions for mail
 */
class MppAryExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			/** deprecated functions */
			new TwigFunction('mpp_ary', [MppAryRuntime::class, 'get'], ['needs_context' => true]),
			new TwigFunction('mpp_anon_ary', [MppAryRuntime::class, 'get_anon'], ['needs_context' => true]),
			new TwigFunction('mpp_guest_ary', [MppAryRuntime::class, 'get_guest'], ['needs_context' => true]),
			new TwigFunction('mpp_admin_ary', [MppAryRuntime::class, 'get_admin'], ['needs_context' => true]),
		];
	}

  public function getFilters ():array
  {
    return [
      new TwigFilter('mpp', [MppAryRuntime::class, 'mpp'], ['needs_context' => true]),
    ];
  }
}
