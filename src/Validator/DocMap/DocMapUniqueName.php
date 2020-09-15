<?php declare(strict_types=1);

namespace App\Validator\DocMap;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class DocMapUniqueName extends Constraint
{
    public function getTargets():string
    {
        return self::CLASS_CONSTRAINT;
    }
}