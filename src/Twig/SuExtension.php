<?php declare(strict_types=1);

namespace App\Twig;

use App\Cnst\RoleCnst;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Twig\Attribute\AsTwigFunction;

class SuExtension
{
	public function __construct(
		private readonly SessionUserService $su,
		private readonly UserCacheService $user_cache_service
	)
	{
	}

  #[AsTwigFunction(name: 'su_role')]
	public function su_role(string $role):bool
	{
		return $role === $this->su->role();
	}

  #[AsTwigFunction(name: 'su_ary')]
	public function su_ary():array
	{
		return $this->su->ary();
	}

  #[AsTwigFunction(name: 'su_is_master')]
	public function su_is_master():bool
	{
		return $this->su->is_master();
	}

  #[AsTwigFunction(name: 'su_is_owner')]
	public function su_is_owner(int $object_author_id):bool
	{
		return $this->su->is_owner($object_author_id);
	}

  #[AsTwigFunction(name: 'su_id')]
	public function su_id():int
	{
		return $this->su->id();
	}

  #[AsTwigFunction(name: 'su_schema')]
	public function su_schema():string
	{
		return $this->su->schema();
	}

  #[AsTwigFunction(name: 'su_is_system_self')]
	public function su_is_system_self():bool
	{
		return $this->su->is_system_self();
	}

  #[AsTwigFunction(name: 'su_logins_role_short')]
	public function su_logins_role_short():array
	{
		$out_ary = [];

		foreach($this->su->logins() as $schema => $id)
		{
			if ($id === 'master')
			{
				$out_ary[$schema] = 'a';
				continue;
			}

			$role = $this->user_cache_service->get($id, $schema)['role'];

			$out_ary[$schema] = RoleCnst::SHORT[$role];
		}

		return $out_ary;
	}
}
