<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\SystemsService;

class system
{
	protected $systems_service;

	public function __construct(
		SystemsService $systems_service
	)
	{
		$this->systems_service = $systems_service;
	}

	public function get(string $schema):string
	{
		return $this->systems_service->get_system($schema);
	}
}
