<?php

namespace util;

use service\service\xdb;
use util\user;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

class user_provider implements UserProviderInterface
{
	private $xdb;

	public function __construct(xdb $xdb)
	{
		$this->xdb = $xdb;
	}

	/**
	 * @param string username is actually email (Symfony hack)
	 * @return user
	 */

    public function loadUserByUsername(string $username)
    {
        $data = $this->xdb->get('user_auth_' . $username)

        if ($data === '{}')
        {
			throw new UsernameNotFoundException(
				sprintf('Username "%s" does not exist.', $username)
			);
        }

		$data = json_decode($data, true);

		return new user($username, $data['password'], $data['salt'], $data['roles']);
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof user)
		{
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return user::class === $class;
    }
}
