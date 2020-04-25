<?php

namespace App\Security;

use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    protected $user_cache_service;

    public function __construct(
        UserCacheService $user_cache_service,
        SessionUserService $su
    )
    {
        $this->user_cache_service = $user_cache_service;
        $this->su = $su;
    }

    /**
    * For switch user or remember me in Symfony security
     */
    public function loadUserByUsername($username)
    {
        throw new \LogicException('UserProvider::loadUserByUsername is not implemented in eLAND.');

        $user = new User();
        return $user;
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User)
        {
            throw new UnsupportedUserException('Invalid user class "' . get_class($user) . '".');
        }

        // just a dummy user in eLAND

        $fresh_user = new User();
        return $fresh_user;
    }

    public function supportsClass($class)
    {
        return User::class === $class;
    }
}
