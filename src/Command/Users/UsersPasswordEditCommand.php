<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use App\Validator\Password\PasswordStrength;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class UsersPasswordEditCommand Implements CommandInterface
{
    #[NotBlank(groups: ['user', 'admin'])]
    #[Length(min: 5, max: 100, groups: ['user', 'admin'])]
    #[PasswordStrength(groups: ['user'])]
    public $password;

    #[Type('bool')]
    public $notify;
}
