<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\SessionUserService;
use Twig\Extension\RuntimeExtensionInterface;

class SRoleRuntime implements RuntimeExtensionInterface
{
	protected $su;

	public function __construct(
		SessionUserService $su
	)
	{
		$this->su = $su;
	}

	public function has_role(string $role):bool
	{
		return $role === $this->su->role();
	}

	public function is_s_master():bool
	{
		return $this->su->is_master();
	}

	public function is_s_elas_guest():bool
	{
		return $this->su->is_elas_guest();
	}

	public function get_s_id():int
	{
		return $this->su->id();
	}

	public function get_s_schema():string
	{
		return $this->su->schema();
	}

	public function is_s_system_self():bool
	{
		return $this->su->is_system_self();
	}
}