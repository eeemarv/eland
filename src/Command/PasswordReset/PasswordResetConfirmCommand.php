<?php declare(strict_types=1);

namespace App\Command\PasswordReset;

use App\Command\CommandInterface;
use App\Validator\Password\PasswordStrength;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class PasswordResetConfirmCommand implements CommandInterface
{
    #[Sequentially([
        new NotBlank(),
        new Length(min: 5, max: 100),
        new PasswordStrength(),
    ])]
    public $password;
}
