<?php declare(strict_types=1);

namespace App\Command\Users;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class UsersPasswordSetAdminCommand
{
    public $password;
    public $notify;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('password', new Sequentially([
            new NotBlank(),
            new Length(['min' => 5, 'max' => 100]),
        ]));
    }
}
