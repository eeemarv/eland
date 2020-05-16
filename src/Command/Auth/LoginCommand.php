<?php declare(strict_types=1);

namespace App\Command\Auth;

use App\Validator\Login;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Mapping\ClassMetadata;


class LoginCommand
{
    public $login;
    public $password;
    public $is_master;
    public $id;
    public $password_hashing_updated;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('login', new NotBlank());
        $metadata->addPropertyConstraint('password', new NotBlank());
        $metadata->addPropertyConstraint('login', new Length(['min' => 2, 'max' => 100]));
        $metadata->addPropertyConstraint('password', new Length(['min' => 5, 'max' => 100]));
        $metadata->addConstraint(new Login(['groups' => ['Login']]));

        $metadata->setGroupSequence(['LoginCommand', 'Login']);
    }
}
