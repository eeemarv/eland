<?php declare(strict_types=1);

namespace App\Twig;

use service\user_cache;
use service\systems;
use app\cnst\rolecnst;

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

	private function get_ary(
		string $role_short,
		string $et,
		string $schema
	):array
	{
		$system = $this->systems->get_system($schema);

		$mpp_ary = [
			'system'	=> $system,
		];

		if ($et !== '')
		{
			$mpp_ary['et'] = $et;
		}

		if ($role_short !== '')
		{
			$mpp_ary['role_short'] = $role_short;
 		}

		return $mpp_ary;
	}

	public function get(array $context, int $id, string $schema):array
	{
		$role = $this->user_cache->get($id, $schema)['accountrole'];
		$role_short = rolecnst::SHORT[$role] ?? '';

		return $this->get_ary($role_short, $context['et'] ?? '', $schema);
	}

	public function get_admin(array $context, string $schema):array
	{
		return $this->get_ary('a', $context['et'] ?? '', $schema);
	}

	public function get_anon(array $context, string $schema):array
	{
		return $this->get_ary('', $context['et'] ?? '', $schema);
	}
}
