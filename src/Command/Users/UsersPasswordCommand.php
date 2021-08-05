<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use App\Validator\PasswordStrength;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class UsersPasswordCommand Implements CommandInterface
{
    public $password;
    public $notify;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('password', new Sequentially([
            new NotBlank(['groups' => ['user', 'admin']]),
            new Length(['min' => 5, 'max' => 100, 'groups' => ['user', 'admin']]),
            new PasswordStrength(['groups' => ['user']]),
        ]));
    }
}
