<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\PageParamsService;
use Twig\Extension\RuntimeExtensionInterface;

class PpRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected PageParamsService $pp
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

	public function has_role(string $role):bool
	{
		switch($role)
		{
			case 'anonymous':
				return $this->pp->is_anonymous();
				break;
			case 'guest':
				return $this->pp->is_guest();
				break;
			case 'user':
				return $this->pp->is_user();
				break;
			case 'admin':
				return $this->pp->is_admin();
				break;
			case 'master':
				return $this->su->is_master();
				break;
			default:
				return false;
				break;
		}

		return false;
	}
}
