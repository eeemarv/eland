<?php

namespace eland;

use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\DBAL\Connection as db;
use Predis\Client as redis;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Session\Session;
use eland\this_group;

class user implements UserInterface
{
	protected $this_group;
	protected $monolog;
	protected $session;

	protected $schema;

	protected $id;
	protected $role;

	protected $data;

	protected $logins;

	protected $possible_roles = [
		'ROLE_ANONUMOUS' 	=> 'anonymous',
		'ROLE_GUEST'		=> 'guest',
		'ROLE_INTERLETS'	=> 'interlets',
		'ROLE_USER'			=> 'user',
		'ROLE_ADMIN'		=> 'admin',
	];

	public function __construct(this_group $this_group, Logger $monolog, Session $session, string $page_access)
	{
		$this->this_group = $this_group;
		$this->monolog = $monolog;
		$this->session = $session;
		$this->page_access = $page_access;

		$this->schema = $_GET['s'] ?? $this->this_group->get_schema();

		$this->role = $_GET['r'] ?? 'anonymous';
		$this->id = $_GET['u'] ?? false;

		$this->logins = $this->session->get('logins') ?? [];

		if (!count($this->logins))
		{
			if ($this->role != 'anonymous')
			{
				$this->monolog->debug('redirect a');
				redirect_login();
			}
		}

		if (!$this->id)
		{
			if ($this->page_access != 'anonymous')
			{
				if (isset($this->logins[$this->schema]) && ctype_digit((string) $this->logins[$this->schema]))
				{
					$this->id = $this->logins[$this->schema];

					$location = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

					$get = $_GET;

					unset($get['u'], $get['s'], $get['r']);

					$this->data = readuser($this->id, false, $this->schema);

					$get['r'] = $this->data['accountrole'];
					$get['u'] = $this->id;

					if ($this->schema != $this->this_group->get_schema())
					{
						$get['s'] = $this->schema;
					}

					$this->monolog->debug('redirect -> add user params');

					$get = http_build_query($get);
					header('Location: ' . $location . '?' . $get);
					exit;

				}

				$this->monolog->debug('redirect (no numeric user id on auth page -> login');
				redirect_login();
			}

			if ($this->role != 'anonymous')
			{
				$this->monolog->debug('redirect login (attempt anonymous page with user params)');
				redirect_login();
			}
		}
		else if (!isset($this->logins[$this->schema]))
		{
			if ($this->role != 'anonymous')
			{
				$this->monolog->debug('redirect login (no login for this schema)');
				redirect_login();
			}

			//
		}
		


	}

	public function is_own_group()
	{
		return $this->schema === $this->this_group->get_schema();
	}





	public function getRoles()
	{
		return ['ROLE_USER'];
	}

	public function getPassword()
	{
		return '';
	}

	public function getSalt()
	{
		return null;
	}

	public function getUsername()
	{
		return '';
	}

	public function eraseCredentials()
	{
	}
}
