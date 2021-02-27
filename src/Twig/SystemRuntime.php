<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\SystemsService;
use Twig\Extension\RuntimeExtensionInterface;

class SystemRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected SystemsService $systems_service
	)
	{
	}

	public function get(string $schema):string
	{
		return $this->systems_service->get_system($schema);
	}
}
