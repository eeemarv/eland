<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LinkUrlExtension extends AbstractExtension
{

	public function getFunctions():array
	{
		return [
			new TwigFunction('context_url', [LinkUrlRuntime::class, 'context_url']),
			new TwigFunction('context_url_open', [LinkUrlRuntime::class, 'context_url_open']),
		];
	}
}
