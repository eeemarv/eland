<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Twig\Extension\RuntimeExtensionInterface;

class PpRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected PageParamsService $pp,
		protected SessionUserService $su
	)
	{
	}

	public function get_ary():array
	{
		return $this->pp->ary();
	}

	public function get_schema():string
	{
		return $this->pp->schema();
	}

	public function get_role():string
	{
		return $this->pp->role();
	}

	public function is_admin():bool
	{
		return $this->pp->is_admin();
	}

	public function is_user():bool
	{
		return $this->pp->is_user();
	}

	public function is_guest():bool
	{
		return $this->pp->is_guest();
	}
}
