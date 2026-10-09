<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Twig\Extension\RuntimeExtensionInterface;

class PpRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		private readonly PageParamsService $pp,
		private readonly SessionUserService $su
	)
	{
	}

	public function get_schema():string
	{
		return $this->pp->schema();
	}

	public function get_role():string
	{
		return $this->pp->role();
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

  public function pp(
    array $params,
    int|null $id = null,
    string|null $role_short = null,
    string|null $status = null,
  ):array
  {
    return [
      ...$params,
      ...$this->pp->ary(),
      ...(isset($id) ? ['id' => $id] : []),
      ...(isset($role_short) ? ['role_short' => $role_short] : []),
      ...(isset($status) ? ['status' => $status] : []),
    ];
  }
}
