<?php declare(strict_types=1);

namespace twig;

class pp_role
{
	protected $pp_anonymous;
	protected $pp_guest;
	protected $pp_user;
	protected $pp_admin;
	protected $s_master;
	protected $s_elas_guest;

	public function __construct(
		bool $pp_anonymous,
		bool $pp_guest,
		bool $pp_user,
		bool $pp_admin,
		bool $s_master,
		bool $s_elas_guest
	)
	{
		$this->pp_anonymous = $pp_anonymous;
		$this->pp_guest = $pp_guest;
		$this->pp_user = $pp_user;
		$this->pp_admin = $pp_admin;
		$this->s_master = $s_master;
		$this->s_elas_guest = $s_elas_guest;
	}

	public function has_role(string $role):bool
	{
		switch($role)
		{
			case 'anonymous':
				return $this->pp_anonymous;
				break;
			case 'guest':
				return $this->pp_guest;
				break;
			case 'user':
				return $this->pp_user;
				break;
			case 'admin':
				return $this->pp_admin;
				break;
			case 'master':
				return $this->s_master;
				break;
			case 'elas_guest':
				return $this->s_elas_guest;
				break;
			default:
				return false;
				break;
		}

		return false;
	}
}
