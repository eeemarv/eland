<?php declare(strict_types=1);

namespace App\Validator\Password;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PasswordStrength extends Constraint
{
}