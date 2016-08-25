<?php

namespace eland;

use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\DBAL\Connection as db;
use Predis\Client as redis;

class user implements UserInterface;
{
	private $schema;
	private $host;

	public function __construct()
	{

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
