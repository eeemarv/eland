<?php declare(strict_types=1);

namespace App\Command\PasswordReset;

use App\Command\CommandInterface;
use App\Validator\EmailUniqueToActiveUser;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class PasswordResetCommand implements CommandInterface
{
    public $email;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('email', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Email(),
                new EmailUniqueToActiveUser(),
            ],
        ]));
    }
}