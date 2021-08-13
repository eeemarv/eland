<?php declare(strict_types=1);

namespace App\Validator\Contact;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class UrlContact extends Constraint
{
    public function getTargets():string
    {
        return self::CLASS_CONSTRAINT;
    }
}