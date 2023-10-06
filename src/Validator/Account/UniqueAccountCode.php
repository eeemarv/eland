<?php declare(strict_types=1);

namespace App\Validator\Account;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class UniqueAccountCode extends Constraint
{
    public function getTargets():string
    {
        return self::CLASS_CONSTRAINT;
    }
}