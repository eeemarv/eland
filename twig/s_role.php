<?php declare(strict_types=1);

namespace twig;

class s_role
{
	protected $s_role;

	public function __construct(
		string $s_role
	)
	{
		$this->s_role = $s_role;
	}

	public function has_role(string $role):bool
	{
		return $role === $this->s_role;
	}
}
