<?php declare(strict_types=1);

namespace twig;

use service\systems;

class system
{
	protected $systems;

	public function __construct(
		systems $systems
	)
	{
		$this->systems = $systems;
	}

	public function get(string $schema):string
	{
		return $this->systems->get_system($schema);
	}
}
