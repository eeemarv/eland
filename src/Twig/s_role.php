<?php declare(strict_types=1);

namespace App\Twig;

class s_role
{
	protected $s_role;

	public function __construct(
		string $s_role,
		int $s_id,
		string $s_schema,
		bool $s_master,
		bool $s_elas_guest,
		bool $s_system_self
	)
	{
		$this->s_role = $s_role;
		$this->s_id = $s_id;
		$this->s_schema = $s_schema;
		$this->s_master = $s_master;
		$this->s_elas_guest = $s_elas_guest;
		$this->s_system_self = $s_system_self;
	}

	public function has_role(string $role):bool
	{
		return $role === $this->s_role;
	}

	public function is_s_master():bool
	{
		return $this->s_master;
	}

	public function is_s_elas_guest():bool
	{
		return $this->s_elas_guest;
	}

	public function get_s_id():int
	{
		return $this->s_id;
	}

	public function get_s_schema():string
	{
		return $this->s_schema;
	}

	public function is_s_system_self():bool
	{
		return $this->s_system_self;
	}
}
