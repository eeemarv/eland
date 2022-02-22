<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use App\Validator\Password\PasswordStrength;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class UsersPasswordEditCommand Implements CommandInterface
{
    #[Sequentially([
        new NotBlank(groups: ['user', 'admin']),
        new Length(min: 5, max: 100, groups: ['user', 'admin']),
        new PasswordStrength(groups: ['user']),
    ])]
    public $password;

    #[Type('bool')]
    public $notify;
}
