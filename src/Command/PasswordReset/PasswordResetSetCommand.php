<?php declare(strict_types=1);

namespace App\Command\PasswordReset;

use App\Command\CommandInterface;
use App\Validator\PasswordStrength;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class PasswordResetSetCommand implements CommandInterface
{
    public $password;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('password', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Length(['min' => 5, 'max' => 100]),
                new PasswordStrength(),
            ],
        ]));
    }
}
