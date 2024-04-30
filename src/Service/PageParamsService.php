<?php declare(strict_types=1);

namespace App\Service;

use App\Cnst\RoleCnst;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Autoconfigure(lazy: true)]
class PageParamsService
{
	protected Request $request;
	protected null|string $role_short;
	protected string $role;
	protected string $system;
	protected string $schema;
	protected array $edit;
	protected array $ary;

	protected bool $is_admin;
	protected bool $is_user;
	protected bool $is_guest;
	protected bool $is_anonymous;

	protected null|string $org_system;
	protected null|string $org_schema;
	protected string $route;

	public function __construct(
		protected RequestStack $request_stack,
		protected SystemsService $systems_service,
		#[Autowire('%env(base64:APP_SYSTEM_REDIRECTS)%')]
		protected string $env_app_system_redirects
	)
	{
		$this->init();
	}

	private function init():void
	{
		$this->request = $this->request_stack->getCurrentRequest();
		$this->route = $this->request->attributes->get('_route');
		$this->role_short = $this->request->attributes->get('role_short');

		if (isset($this->role_short))
		{
			$this->role = RoleCnst::LONG[$this->role_short] ?? 'anonymous';
		}
		else
		{
			$this->role = 'anonymous';
		}

		$this->is_admin = $this->role === 'admin';
		$this->is_user = $this->role === 'user';
		$this->is_guest = $this->role === 'guest';
		$this->is_anonymous = $this->role === 'anonymous';

		$system = $this->request->attributes->get('system');

		if (!isset($system) || $system === '')
		{
			throw new NotFoundHttpException('No system defined.');
		}

		$schema = $this->systems_service->get_schema($system);

		if (!isset($schema))
		{
			$system_redirects = json_decode($this->env_app_system_redirects, true) ?? [];

			if (isset($system_redirects[$system]))
			{
				header('Location: ' . $system_redirects[$system]);
				exit;
			}

			throw new NotFoundHttpException('Could not find system "' . $system . '"');
		}

		$this->system = $system;
		$this->schema = $schema;

		$this->org_system = $this->request->query->get('os');

		if ($this->org_system === $this->system
			|| !$this->is_guest
			|| !$this->systems_service->get_schema($this->org_system))
		{
			$this->org_system = null;
		}

		$this->org_schema = isset($this->org_system) ? $this->systems_service->get_schema($this->org_system) : null;

		$this->ary = [];

		$this->ary['system'] = $this->system;

		if (isset($this->org_system))
		{
			$this->ary['os'] = $this->org_system;
		}
		else
		{
			$edit = $this->request->query->all('edit');

			if ($edit && isset($edit['en']) && $edit['en'] === '1')
			{
				$this->edit['en'] = '1';
				if (isset($edit['route']) && $edit['route'] === '1')
				{
					$this->edit['route'] = '1';
				}
				if (isset($edit['role']) && $edit['role'] === '1')
				{
					$this->edit['role'] = '1';
				}
				if (isset($edit['inline']) && $edit['inline'] === '1')
				{
					$this->edit['inline'] = '1';
				}
				$this->ary['edit'] = $this->edit;
			}
		}

		if (isset($this->role_short))
		{
			$this->ary['role_short'] = $this->role_short;
		}
	}

	public function route():string
	{
		return $this->route;
	}

	public function role():string
	{
		return $this->role;
	}

	public function role_short():null|string
	{
		return $this->role_short;
	}

	public function is_admin():bool
	{
		return $this->is_admin;
	}

	public function is_user():bool
	{
		return $this->is_user;
	}

	public function is_guest():bool
	{
		return $this->is_guest;
	}

	public function is_anonymous():bool
	{
		return $this->is_anonymous;
	}

	public function edit():array
	{
		return $this->edit;
	}

	public function edit_en():bool
	{
		return isset($this->edit['en']);
	}

	public function edit_route_en():bool
	{
		return isset($this->edit['route']);
	}

	public function edit_role_en():bool
	{
		return isset($this->edit['role']);
	}

	public function edit_inline_en():bool
	{
		return isset($this->edit['inline']);
	}

	public function system():string
	{
		return $this->system;
	}

	public function schema():string
	{
		return $this->schema;
	}

	public function org_system():null|string
	{
		return $this->org_system;
	}

	public function org_schema():null|string
	{
		return $this->org_schema;
	}

	public function ary():array
	{
		return $this->ary;
	}
}
