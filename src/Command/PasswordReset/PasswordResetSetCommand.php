<?php declare(strict_types=1);

namespace App\Command\PasswordReset;

use App\Validator\PasswordStrength;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class PasswordResetSetCommand
{
    public $password;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('password', new NotBlank());
        $metadata->addPropertyConstraint('password', new Length(['min' => 5, 'max' => 100]));
        $metadata->addPropertyConstraint('password', new PasswordStrength(['groups' => ['Strength']]));

        $metadata->setGroupSequence(['PasswordResetSetCommand', 'Strength']);
    }
}
