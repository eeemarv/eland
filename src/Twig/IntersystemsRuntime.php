<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\SystemsService;
use Twig\Extension\RuntimeExtensionInterface;

class IntersystemsRuntime implements RuntimeExtensionInterface
{
	public function __construct(
    private readonly SystemsService $systems_service,
	)
	{
	}

	public function get_schemas(string $schema):array
	{
		return array_keys($this->systems_service->get_inter_ary($schema));
	}
}
