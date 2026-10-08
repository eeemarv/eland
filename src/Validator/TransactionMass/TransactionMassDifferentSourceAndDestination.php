<?php declare(strict_types=1);

namespace App\Validator\TransactionMass;

use Symfony\Component\Validator\Constraint;

#[\Attribute(flags: \Attribute::TARGET_CLASS)]
class TransactionMassDifferentSourceAndDestination extends Constraint
{
  public function getTargets():string
  {
    return self::CLASS_CONSTRAINT;
  }
}