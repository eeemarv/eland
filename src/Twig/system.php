<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\Systems;

class system
{
	protected $systems;

	public function __construct(
		Systems $systems
	)
	{
		$this->systems = $systems;
	}

	public function get(string $schema):string
	{
		return $this->systems->get_system($schema);
	}
}
