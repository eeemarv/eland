<?php declare(strict_types=1);

namespace App\Validator\Email;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class EmailUniqueToActiveUser extends Constraint
{
}