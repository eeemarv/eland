<?php declare(strict_types=1);

namespace App\Service;

use App\Cnst\RoleCnst;
use App\Service\UserCacheService;
use App\Service\PageParamsService;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionUserService
{
	protected SessionInterface $session;
	protected PageParamsService $pp;
	protected UserCacheService $user_cache_service;

	protected string $schema;
	protected string $system;
	protected bool $is_system_self;
	protected int $id;
	protected array $logins;
	protected array $user = [];
	protected string $role;
	protected string $role_short;
	protected bool $is_anonymous;
	protected bool $is_guest;
	protected bool $is_user;
	protected bool $is_admin;
	protected bool $is_master;
	protected array $ary;

	public function __construct(
		SessionInterface $session,
		PageParamsService $pp,
		UserCacheService $user_cache_service
	)
	{
		$this->session = $session;
		$this->pp = $pp;
		$this->user_cache_service = $user_cache_service;

		$this->load_session();
		$this->load_user_role();
	}

	private function load_session():void
	{
		$this->logins = $this->session->get('logins') ?? [];
	}

	private function load_user_role():void
	{
		$this->schema = $this->pp->org_schema() ?: $this->pp->schema();
		$this->system = $this->pp->org_system() ?: $this->pp->system();
		$this->is_system_self = $this->schema() === $this->pp->schema();

		$id = $this->logins[$this->schema] ?? 0;

		$this->is_master = $id === 'master';

		$role = $this->is_master ? 'admin' : 'anonymous';

		$this->id = ctype_digit((string) $id) ? $id : 0;

		if ($this->id)
		{
			$this->user = $this->user_cache_service->get($this->id, $this->schema);
			$role = $this->user['accountrole'];
			$role = $role === 'interlets' ? 'guest' : $role;
			$role = in_array($role, ['user', 'admin', 'guest']) ? $role : 'anonymous';
		}

		$this->role = $role;
		$this->role_short = RoleCnst::SHORT[$this->role] ?? '';

		$this->is_anonymous = $this->role === 'anonymous';
		$this->is_guest = $this->role === 'guest';
		$this->is_user = $this->role === 'user';
		$this->is_admin = $this->role === 'admin';

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
		$this->logins[$schema] = $user_id;
		$this->session->set('logins', $this->logins);
		$this->load_user_role();
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

	public function is_anonymous():bool
	{
		return $this->is_anonymous;
	}

	public function is_guest():bool
	{
		return $this->is_guest;
	}

	public function is_user():bool
	{
		return $this->is_user;
	}

	public function is_admin():bool
	{
		return $this->is_admin;
	}

	public function is_master():bool
	{
		return $this->is_master;
	}

	public function is_system_self():bool
	{
		return $this->is_system_self;
	}

	public function is_owner(int $item_owner_id):bool
	{
		if (!$item_owner_id)
		{
			return false;
		}

		if (!$this->id)
		{
			return false;
		}

		if ($this->pp->is_guest())
		{
			return false;
		}

		if (!$this->is_system_self)
		{
			return false;
		}

		return $item_owner_id === $this->id;
	}

	public function has_open_mollie_payment():bool
	{
		return isset($this->user['has_open_mollie_payment'])
			&& $this->user['has_open_mollie_payment'];
	}

	public function code():string
	{
		return $this->user['code'] ?? '';
	}

	public function name():string
	{
		return $this->user['name'] ?? '';
	}
}
