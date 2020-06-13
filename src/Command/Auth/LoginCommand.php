<?php declare(strict_types=1);

namespace App\Command\Auth;

use App\Validator\Auth\Login;
use App\Validator\PasswordStrength;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Sequentially;
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
        $metadata->addPropertyConstraint('login', new Sequentially([
            new NotBlank(),
            new Length(['min' => 2, 'max' => 100]),
        ]));
        $metadata->addPropertyConstraint('password', new Sequentially([
            new NotBlank(),
            new Length(['min' => 5, 'max' => 100]),
        ]));
        $metadata->addConstraint(new Login(['groups' => ['Login']]));
        $metadata->setGroupSequence(['LoginCommand', 'Login']);
    }
}
