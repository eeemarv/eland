<?php declare(strict_types=1);

namespace App\Validator\Login;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class Login extends Constraint
{
    public function getTargets():string
    {
        return self::CLASS_CONSTRAINT;
    }
}