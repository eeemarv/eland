<?php declare(strict_types=1);

namespace App\Service;

use App\DTO\Schema;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageParamsService
{
	private Request $request;
	private string|null $role_short;
	private string $role;
	private string $schema;
  private Schema $schema_o;
	private array $edit;
	private array $ary;

	private bool $is_admin;
	private bool $is_user;
	private bool $is_guest;
	private bool $is_anonymous;

	private string|null $org_schema;
	private string $route;

	public function __construct(
		protected RequestStack $request_stack,
		protected SystemsService $systems_service,
		#[Autowire('%env(base64:APP_SYSTEM_REDIRECTS)%')]
		protected string $env_app_system_redirects
	)
	{
	}

	private function init():void
	{
		$this->request = $this->request_stack->getCurrentRequest();
		$this->route = $this->request->attributes->get('_route');
		$this->role_short = $this->request->attributes->get('role_short');

    switch ($this->role_short)
    {
      case 'a':
        $this->role = 'admin';
        break;
      case 'u':
        $this->role = 'user';
        break;
      case 'g':
        $this->role = 'guest';
        break;
      case null:
        $this->role = 'anonymous';
        break;
      default:
        throw new NotFoundHttpException('No valid role for route');
        break;
    }

		$this->is_admin = $this->role === 'admin';
		$this->is_user = $this->role === 'user';
		$this->is_guest = $this->role === 'guest';
		$this->is_anonymous = $this->role === 'anonymous';

		$this->schema = $this->request->attributes->get('schema');

		if (!$this->schema)
		{
			throw new NotFoundHttpException('No system defined.');
		}

		if (!$this->systems_service->has_schema($this->schema))
		{
			$schema_redirects = json_decode($this->env_app_system_redirects, true) ?? [];

			if (isset($schema_redirects[$this->schema]))
			{
				header('Location: ' . $schema_redirects[$this->schema]);
				exit;
			}

			throw new NotFoundHttpException('System/schema "' . $this->schema . '" not found.');
		}

    $this->schema_o = new Schema($this->schema);

		$this->org_schema = $this->request->query->get('os');

    if ($this->org_schema)
    {
      if ($this->org_schema === $this->schema
        || !$this->is_guest
        || !$this->systems_service->has_schema($this->org_schema))
      {
        $this->org_schema = null;
      }
    }

		$this->ary = [];

		if ($this->schema)
		{
			$this->ary['schema'] = $this->schema;

			if ($this->org_schema)
			{
				$this->ary['os'] = $this->org_schema;
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

			if ($this->role_short)
			{
				$this->ary['role_short'] = $this->role_short;
			}
		}
	}

	public function route():string
	{
    if (!isset($this->route))
    {
      $this->init();
    }
		return $this->route;
	}

	public function role():string
	{
    if (!isset($this->route))
    {
      $this->init();
    }
		return $this->role;
	}

	public function role_short():string|null
	{
    if (!isset($this->route))
    {
      $this->init();
    }
		return $this->role_short;
	}

	public function is_admin():bool
	{
    if (!isset($this->route))
    {
      $this->init();
    }
		return $this->is_admin;
	}

	public function is_user():bool
	{
    if (!isset($this->route))
    {
      $this->init();
    }
		return $this->is_user;
	}

	public function is_guest():bool
	{
    if (!isset($this->route))
    {
      $this->init();
    }
		return $this->is_guest;
	}

	public function is_anonymous():bool
	{
    if (!isset($this->route))
    {
      $this->init();
    }
		return $this->is_anonymous;
	}

	public function edit():array
	{
    if (!isset($this->route))
    {
      $this->init();
    }
		return $this->edit;
	}

	public function edit_en():bool
	{
    if (!isset($this->route))
    {
      $this->init();
    }
		return isset($this->edit['en']);
	}

	public function edit_route_en():bool
	{
    if (!isset($this->route))
    {
      $this->init();
    }
		return isset($this->edit['route']);
	}

	public function edit_role_en():bool
	{
    if (!isset($this->route))
    {
      $this->init();
    }
		return isset($this->edit['role']);
	}

	public function edit_inline_en():bool
	{
    if (!isset($this->route))
    {
      $this->init();
    }
		return isset($this->edit['inline']);
	}

	public function schema():string
	{
    if (!isset($this->route))
    {
      $this->init();
    }
		return $this->schema;
	}

	public function schema_o():Schema
	{
    if (!isset($this->route))
    {
      $this->init();
    }
		return $this->schema_o;
	}

	public function org_schema():string|null
	{
    if (!isset($this->route))
    {
      $this->init();
    }
		return $this->org_schema;
	}

	public function ary():array
	{
    if (!isset($this->route))
    {
      $this->init();
    }
		return $this->ary;
	}
}
