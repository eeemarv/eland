<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\IntersystemsService;
use Twig\Extension\RuntimeExtensionInterface;

class IntersystemsRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected IntersystemsService $intersystems_service
	)
	{
	}

	public function get_schemas(string $schema):array
	{
		return array_values($this->intersystems_service->get_eland_accounts_schemas($schema));
	}
}
