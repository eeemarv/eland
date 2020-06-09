<?php declare(strict_types=1);

namespace App\Validator\Auth;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Login extends Constraint
{
    public function getTargets():string
    {
        return self::CLASS_CONSTRAINT;
    }
}