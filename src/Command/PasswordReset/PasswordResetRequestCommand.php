<?php declare(strict_types=1);

namespace App\Command\PasswordReset;

use App\Validator\EmailUniqueToActiveUser;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class PasswordResetRequestCommand
{
    public $email;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('email', new NotBlank());
        $metadata->addPropertyConstraint('email', new Email());
        $metadata->addPropertyConstraint('email', new EmailUniqueToActiveUser(['groups' => ['Unique']]));
        $metadata->setGroupSequence(['PasswordResetRequestCommand', 'Unique']);
    }
}
