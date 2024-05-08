<?php declare(strict_types=1);

namespace App\Twig;

use App\Cache\SystemsCache;
use Twig\Extension\RuntimeExtensionInterface;

class SystemRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected SystemsCache $systems_cache
	)
	{
	}

	public function get(string $schema):string
	{
		return $this->systems_cache->get_system($schema);
	}
}
