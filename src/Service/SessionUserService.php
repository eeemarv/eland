<?php declare(strict_types=1);

namespace App\Service;

use App\Cnst\RoleCnst;
use Symfony\Component\HttpFoundation\Session\Session;
use App\Service\UserCacheService;
use App\Service\PageParamsService;

class SessionUserService
{
	protected $session;
	protected $pp;
	protected $user_cache_service;

	protected $schema;
	protected $is_system_self;
	protected $id;
	protected $logins;
	protected $user = [];
	protected $role;
	protected $role_short;
	protected $is_master;
	protected $is_elas_guest;
	protected $is_admin;
	protected $is_user;
	protected $is_anonymous;
	protected $ary;

	public function __construct(
		Session $session,
		PageParamsService $pp,
		UserCacheService $user_cache_service
	)
	{
		$this->session = $session;
		$this->pp = $pp;
		$this->user_cache_service = $user_cache_service;

		$this->init();
	}

	private function init():void
	{
		$this->schema = $this->pp->org_schema ?: $this->pp->schema;
		$this->system = $this->pp->org_system ?: $this->pp->system;
		$this->is_system_self = $this->schema === $this->pp->schema;

		$this->logins = $this->session->get('logins') ?? [];

		$id = $this->logins[$this->schema] ?? 0;

		$this->is_master = $id === 'master';
		$this->is_elas_guest = $this->is_system_self && $id === 'elas';

		$role = $this->is_master ? 'admin' : 'anonymous';

		$this->id = ctype_digit((string) $id) ? $id : 0;

		if ($this->id)
		{
			$this->user = $this->user_cache_service->get($this->id, $this->schema);
			$role = $this->user['accountrole'];
			$role = in_array($role, ['user', 'admin']) ? $role : 'anonymous';
		}

		$this->role = $role;
		$this->role_short = RoleCnst::SHORT[$this->role];

		$this->is_user = $this->role === 'user';
		$this->is_admin = $this->role === 'admin';
		$this->is_anonymous = $this->role === 'anonymous';

		if ($this->system && $this->role_short)
		{
			$this->ary = [
				'system'		=> $this->system,
				'role_short'	=> $this->role_short,
			];
		}
		else
		{
			$this->ary = [];
		}
	}

	public function set_login(string $schema, int $user_id):void
	{
		$logins = $this->logins;
		$logins[$schema] = $user_id;
		$this->session->set('logins', $logins);
	}

	public function set_elas_guest_login(string $schema):void
	{
		$logins = $this->logins;
		$logins[$schema] = 'elas';
		$this->session->set('logins', $logins);
	}

	public function set_master_login(string $schema):void
	{
		$logins = $this->logins;
		$logins[$schema] = 'master';
		$this->session->set('logins', $logins);
	}

	public function schema():string
	{
		return $this->schema;
	}

	public function system():string
	{
		return $this->system;
	}

	public function id():int
	{
		return $this->id;
	}

	public function logins():array
	{
		return $this->logins;
	}

	public function user():array
	{
		return $this->user;
	}

	public function role():string
	{
		return $this->role;
	}

	public function role_short():string
	{
		return $this->role_short;
	}

	public function ary():array
	{
		return $this->ary;
	}

	public function is_admin():bool
	{
		return $this->is_admin;
	}

	public function is_user():bool
	{
		return $this->is_user;
	}

	public function is_anonymous():bool
	{
		return $this->is_anonymous;
	}

	public function is_master():bool
	{
		return $this->is_master;
	}

	public function is_elas_guest():bool
	{
		return $this->is_elas_guest;
	}

	public function is_system_self():bool
	{
		return $this->is_system_self;
	}

	public function is_owner(int $item_owner_id):bool
	{
		if (!$this->id)
		{
			return false;
		}

		if (!$this->is_system_self)
		{
			return false;
		}

		return $item_owner_id === $this->id;
	}
}
