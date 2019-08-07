<?php declare(strict_types=1);

namespace twig;

use service\user_cache;
use service\systems;
use cnst\role as cnst_role;

class mpp_ary
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

	public function get(array $context, int $id, string $schema):array
	{
		$system = $this->systems->get_system($schema);

		$role = $this->user_cache->get($id, $schema)['accountrole'];
		$role_short = cnst_role::SHORT[$role] ?? 'g';

		$mpp_ary = [
			'system'	=> $system,
		];

		if (isset($context['et']))
		{
			$mpp_ary['et'] = $context['et'];
		}

		if (in_array($role_short, ['u', 'a']))
		{
			$mpp_ary['role_short'] = $role_short;
		}

		return $mpp_ary;
	}
}
