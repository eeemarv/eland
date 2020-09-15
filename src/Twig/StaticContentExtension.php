<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class StaticContentExtension extends AbstractExtension
{
	public function getFunctions():array
    {
        return [
			new TwigFunction('static_content', [StaticContentRuntime::class, 'get']),
        ];
    }
}
