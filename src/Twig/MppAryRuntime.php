<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\UserCacheService;
use App\Service\SystemsService;
use App\Cnst\RoleCnst;
use Twig\Extension\RuntimeExtensionInterface;

class MppAryRuntime implements RuntimeExtensionInterface
{
	protected $user_cache_service;
	protected $systems_service;

	public function __construct(
		UserCacheService $user_cache_service,
		SystemsService $systems_service
	)
	{
		$this->user_cache_service = $user_cache_service;
		$this->systems_service = $systems_service;
	}

	private function get_ary(
		string $role_short,
		string $et,
		string $schema
	):array
	{
		$system = $this->systems_service->get_system($schema);

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
		$role = $this->user_cache_service->get($id, $schema)['accountrole'];
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
