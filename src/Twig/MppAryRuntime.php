<?php declare(strict_types=1);

namespace App\Twig;

use App\Cache\SystemsCache;
use App\Cache\UserCache;
use App\Cnst\RoleCnst;
use Twig\Extension\RuntimeExtensionInterface;

class MppAryRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected UserCache $user_cache,
		protected SystemsCache $systems_cache
	)
	{
	}

	private function get_ary(
		string $role_short,
		string $et,
		string $schema
	):array
	{
		$system = $this->systems_cache->get_system($schema);

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
		$role = $this->user_cache->get($id, $schema)['role'];
		$role_short = RoleCnst::SHORT[$role] ?? '';

		return $this->get_ary($role_short, $context['et'] ?? '', $schema);
	}

	public function get_admin(array $context, string $schema):array
	{
		return $this->get_ary('a', $context['et'] ?? '', $schema);
	}

	public function get_guest(array $context, string $schema):array
	{
		return $this->get_ary('g', $context['et'] ?? '', $schema);
	}

	public function get_anon(array $context, string $schema):array
	{
		return $this->get_ary('', $context['et'] ?? '', $schema);
	}
}
