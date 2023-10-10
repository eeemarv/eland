<?php declare(strict_types=1);

namespace App\Validator\User;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class UniqueUserName extends Constraint
{
    public function getTargets():string
    {
        return self::CLASS_CONSTRAINT;
    }
}