<?php

namespace App\Security;

use App\Service\UserCacheService;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    protected $user_cache_service;

    public function __construct(
        UserCacheService $user_cache_service
    )
    {
        $this->user_cache_service = $user_cache_service;
    }

    /**
     * Symfony calls this method if you use features like switch_user
     * or remember_me.
     * // For eLAND, "username" is "system.id"
     * @param string
     * @return UserInterface
     * @throws UsernameNotFoundException if the user is not found
     */
    public function loadUserByUsername($username)
    {
        if (strpos($username, '.') === false)
        {
            throw new UnsupportedUserException('Wrong user identifier format');
        }

        [$system, $id] = explode('.', $username);

        if (!$system)
        {
            throw new UnsupportedUserException('System missing for user identifier.');
        }

        if (!$id)
        {
            throw new UnsupportedUserException('Id missing for user identifier');
        }

        if (ctype_digit($id))
        {
            $user_data = $this->user_cache_service->get($id, $system);

            if (!$user_data)
            {
                throw new UsernameNotFoundException('De gebruiker werd niet gevonden.');
            }
        }
        else
        {
            throw new UnsupportedUserException('Ongeldig identifier formaat voor gebruiker.');
        }







        // Load a User object from your data source or throw UsernameNotFoundException.
        // The $username argument may not actually be a username:
        // it is whatever value is being returned by the getUsername()
        // method in your User class.
        throw new \Exception('TODO: fill in loadUserByUsername() inside '.__FILE__);
    }

    /**
     * Refreshes the user after being reloaded from the session.
     *
     * When a user is logged in, at the beginning of each request, the
     * User object is loaded from the session and then this method is
     * called. Your job is to make sure the user's data is still fresh by,
     * for example, re-querying for fresh User data.
     *
     * If your firewall is "stateless: true" (for a pure API), this
     * method is not called.
     *
     * @return UserInterface
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User)
        {
            throw new UnsupportedUserException('Invalid user class "' . get_class($user) . '".');
        }

        // Return a User object after making sure its data is "fresh".
        // Or throw a UsernameNotFoundException if the user no longer exists.
        throw new \Exception('TODO: fill in refreshUser() inside '.__FILE__);
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass($class)
    {
        return User::class === $class;
    }
}
