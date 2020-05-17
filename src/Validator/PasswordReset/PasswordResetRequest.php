<?php declare(strict_types=1);

namespace App\Validator\PasswordReset;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class PasswordResetRequest extends Constraint
{
    public function getTargets():string
    {
        return self::CLASS_CONSTRAINT;
    }
}