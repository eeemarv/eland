<?php declare(strict_types=1);

namespace App\Service;

use App\Cnst\RoleCnst;
use App\DTO\Schema;
use App\Service\UserCacheService;
use App\Service\PageParamsService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

class SessionUserService
{
	private readonly Session $session;
	private readonly string $schema;
	private readonly bool $is_system_self;
	private readonly int $id;
	private readonly array $logins;
	private readonly array $user;
	private readonly string $role;
	private readonly string $role_short;
	private readonly bool $is_anonymous;
	private readonly bool $is_guest;
	private readonly bool $is_user;
	private readonly bool $is_admin;
	private readonly bool $is_master;
	private readonly array $ary;

	public function __construct(
		private readonly RequestStack $request_stack,
		private readonly PageParamsService $pp,
		private readonly UserCacheService $user_cache_service
	)
	{
	}

	private function load_session():void
	{
    $this->session = $this->request_stack->getSession();
		$this->logins = $this->session->get('logins') ?? [];
	}

	private function load_user_role():void
	{
		$this->schema = $this->pp->org_schema() ?: $this->pp->schema();
		$this->is_system_self = $this->schema() === $this->pp->schema();

		$id = $this->logins[$this->schema] ?? 0;

		$this->is_master = $id === 'master';

		$role = $this->is_master ? 'admin' : 'anonymous';

		$this->id = ctype_digit((string) $id) ? $id : 0;

		if ($this->id)
		{
			$this->user = $this->user_cache_service->get($this->id, $this->schema);
			$role = $this->user['role'];
			$role = in_array($role, ['user', 'admin', 'guest']) ? $role : 'anonymous';
		}

		$this->role = $role;
		$this->role_short = RoleCnst::SHORT[$this->role] ?? '';

		$this->is_anonymous = $this->role === 'anonymous';
		$this->is_guest = $this->role === 'guest';
		$this->is_user = $this->role === 'user';
		$this->is_admin = $this->role === 'admin';

		if ($this->schema && $this->role_short)
		{
			$this->ary = [
				'schema'		  => $this->schema,
				'role_short'	=> $this->role_short,
			];
		}
		else
		{
			$this->ary = [];
		}
	}

  private function load():void
  {
		$this->load_session();
		$this->load_user_role();
  }

	public function set_login(
    string $schema,
    int $user_id,
  ):void
	{
    if (!isset($this->schema))
    {
      $this->load();
    }
    $logins = $this->logins;
		$logins[$schema] = $user_id;
		$this->session->set('logins', $logins);
	}

	public function set_master_login(
    string $schema
  ):void
	{
    if (!isset($this->schema))
    {
      $this->load();
    }
		$logins = $this->logins;
		$logins[$schema] = 'master';
		$this->session->set('logins', $logins);
	}

	public function schema():string
	{
    if (!isset($this->schema))
    {
      $this->load();
    }
		return $this->schema;
	}

  public function schema_o():Schema
  {
    if (!isset($this->schema))
    {
      $this->load();
    }
    return new Schema($this->schema);
  }

	public function id():int
	{
    if (!isset($this->schema))
    {
      $this->load();
    }
		return $this->id;
	}

	public function logins():array
	{
    if (!isset($this->schema))
    {
      $this->load();
    }
		return $this->logins;
	}

	public function user():array
	{
    if (!isset($this->schema))
    {
      $this->load();
    }
		return $this->user;
	}

	public function role():string
	{
    if (!isset($this->schema))
    {
      $this->load();
    }
		return $this->role;
	}

	public function role_short():string
	{
    if (!isset($this->schema))
    {
      $this->load();
    }
		return $this->role_short;
	}

	public function ary():array
	{
    if (!isset($this->schema))
    {
      $this->load();
    }
		return $this->ary;
	}

	public function is_anonymous():bool
	{
    if (!isset($this->schema))
    {
      $this->load();
    }
		return $this->is_anonymous;
	}

	public function is_guest():bool
	{
    if (!isset($this->schema))
    {
      $this->load();
    }
		return $this->is_guest;
	}

	public function is_user():bool
	{
    if (!isset($this->schema))
    {
      $this->load();
    }
		return $this->is_user;
	}

	public function is_admin():bool
	{
    if (!isset($this->schema))
    {
      $this->load();
    }
		return $this->is_admin;
	}

	public function is_master():bool
	{
    if (!isset($this->schema))
    {
      $this->load();
    }
		return $this->is_master;
	}

	public function is_system_self():bool
	{
    if (!isset($this->schema))
    {
      $this->load();
    }
		return $this->is_system_self;
	}

	public function is_owner(int $item_owner_id):bool
	{
    if (!isset($this->schema))
    {
      $this->load();
    }

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
    if (!isset($this->schema))
    {
      $this->load();
    }

		return isset($this->user['has_open_mollie_payment'])
			&& $this->user['has_open_mollie_payment'];
	}

	public function code():string
	{
    if (!isset($this->schema))
    {
      $this->load();
    }

		return $this->user['code'] ?? '';
	}

	public function name():string
	{
    if (!isset($this->schema))
    {
      $this->load();
    }

		return $this->user['name'] ?? '';
	}
}
