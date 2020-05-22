<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ItemAccessExtension extends AbstractExtension
{
	public function getFunctions():array
	{
		return [
			new TwigFunction('item_visible', [ItemAccessRuntime::class, 'item_visible']),
		];
	}
}
