<?php declare(strict_types=1);

namespace App\Validator\ContactType;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class UniqueContactType extends Constraint
{
    public array $properties = [];

    public function getTargets():string
    {
        return self::CLASS_CONSTRAINT;
    }
}