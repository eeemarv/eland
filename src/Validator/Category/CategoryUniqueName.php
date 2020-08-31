<?php declare(strict_types=1);

namespace App\Validator\Category;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class CategoryUniqueName extends Constraint
{
    public function getTargets():string
    {
        return self::CLASS_CONSTRAINT;
    }
}