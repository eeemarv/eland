<?php declare(strict_types=1);

namespace App\Validator\Tag;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class TagUniqueTxt extends Constraint
{
    public function getTargets():string
    {
        return self::CLASS_CONSTRAINT;
    }
}