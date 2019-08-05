<?php declare(strict_types=1);

namespace twig;

use service\user_cache;
use service\systems;
use cnst\role as cnst_role;

class user_pp_ary
{
	protected $user_cache;
	protected $systems;

	public function __construct(
		user_cache $user_cache,
		systems $systems
	)
	{
		$this->user_cache = $user_cache;
		$this->systems = $systems;
	}

	public function get_self(int $id, string $schema):array
	{
		$system = $this->systems->get_system($schema);

		$role = $this->user_cache->get($id, $schema)['accountrole'];
		$role_short = cnst_role::SHORT[$role] ?? 'g';

		if ($role_short === 'a' || $role_short === 'u')
		{
			return [
				'system'		=> $system,
				'role_short'	=> $role_short,
			];
		}

		return [
			'system'	=> $system,
		];
	}
}
