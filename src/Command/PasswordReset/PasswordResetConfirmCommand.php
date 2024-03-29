<?php declare(strict_types=1);

namespace App\Command\PasswordReset;

use App\Command\CommandInterface;
use App\Validator\Password\PasswordStrength;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class PasswordResetConfirmCommand implements CommandInterface
{
    #[Sequentially(constraints: [
        new NotBlank(groups: ['edit']),
        new Length(min: 5, max: 100, groups: ['edit']),
        new PasswordStrength(groups: ['edit']),
    ])]
    public $password;
}
