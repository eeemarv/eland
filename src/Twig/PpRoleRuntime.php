<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Twig\Extension\RuntimeExtensionInterface;

class PpRoleRuntime implements RuntimeExtensionInterface
{
	protected $pp;
	protected $su;

	public function __construct(
		PageParamsService $pp,
		SessionUserService $su
	)
	{
		$this->pp = $pp;
		$this->su = $su;
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
			case 'elas_guest':
				return $this->su->is_elas_guest();
				break;
			default:
				return false;
				break;
		}

		return false;
	}
}