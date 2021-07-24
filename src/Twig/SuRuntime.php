<?php declare(strict_types=1);

namespace App\Twig;

use App\Cnst\RoleCnst;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Twig\Extension\RuntimeExtensionInterface;

class SuRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected SessionUserService $su,
		protected UserCacheService $user_cache_service
	)
	{
	}

	public function su_role(string $role):bool
	{
		return $role === $this->su->role();
	}

	public function su_ary():array
	{
		return $this->su->ary();
	}

	public function su_is_master():bool
	{
		return $this->su->is_master();
	}

	public function su_is_owner(int $object_author_id):bool
	{
		return $this->su->is_owner($object_author_id);
	}

	public function su_id():int
	{
		return $this->su->id();
	}

	public function su_schema():string
	{
		return $this->su->schema();
	}

	public function su_is_system_self():bool
	{
		return $this->su->is_system_self();
	}

	public function su_logins_role_short():array
	{
		$out_ary = [];

		foreach($this->su->logins() as $schema => $id)
		{
			if ($id === 'master')
			{
				$out_ary[$schema] = 'a';
			}

			$role = $this->user_cache_service->get($id, $schema)['role'];

			$out_ary[$schema] = RoleCnst::SHORT[$role];
		}

		return $out_ary;
	}
}
