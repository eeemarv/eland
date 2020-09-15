<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ConfigExtension extends AbstractExtension
{
	public function getFunctions():array
    {
        return [
			new TwigFunction('config', [ConfigRuntime::class, 'get']),
			new TwigFunction('config_str', [ConfigRuntime::class, 'get_str']),
			new TwigFunction('config_int', [ConfigRuntime::class, 'get_int']),
			new TwigFunction('config_bool', [ConfigRuntime::class, 'get_bool']),
			new TwigFunction('config_ary', [ConfigRuntime::class, 'get_ary']),
        ];
    }
}
