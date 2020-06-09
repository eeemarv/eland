<?php declare(strict_types=1);

namespace App\Command\Users;

use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class UsersDelCommand
{
    public $verify;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('verify', new IsTrue());
    }
}
