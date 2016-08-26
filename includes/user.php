<?php

namespace eland;

use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\DBAL\Connection as db;
use Predis\Client as redis;
use eland\this_group;

class user implements UserInterface;
{
	protected $schema;
	protected $host;
	protected $this_group;

	public function __construct(this_group $this_group)
	{
		$this->this_group = $this_group;


		$p_role = $_GET['r'] ?? 'anonymous';
		$p_user = $_GET['u'] ?? false;
		$p_schema = $_GET['s'] ?? false;
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
