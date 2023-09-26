<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    protected string $username = '';
    protected array $roles = [];
    protected string $password; // hashed password

    public function getUsername():string
    {
        return (string) $this->username;
    }

    public function setUsername(string $username):self
    {
        $this->username = $username;
        return $this;
    }

    public function getRoles():array
    {
        return array_unique($this->roles);
    }

    public function setRoles(array $roles):self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword():string
    {
        // hashed password
        return (string) $this->password;
    }

    public function setPassword(string $password):self
    {
        // hashed password
        $this->password = $password;
        return $this;
    }

    public function getSalt():null|string
    {
        return null;
        // not used
    }

    public function eraseCredentials():void
    {
        // obsolete, not used
    }

    public function getUserIdentifier():string
    {
        // dummy
        return '';
    }
}
