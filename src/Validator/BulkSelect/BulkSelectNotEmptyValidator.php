<?php declare(strict_types=1);

namespace App\Validator\BulkSelect;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class BulkSelectNotEmptyValidator extends ConstraintValidator
{
  public function validate($selected, Constraint $constraint):void
  {
    if (!$constraint instanceof BulkSelectNotEmpty)
    {
      throw new UnexpectedTypeException($constraint, BulkSelectNotEmpty::class);
    }

    if (!isset($selected)){
      $selected = '';
    }

    if (!is_string($selected))
    {
      throw new UnexpectedTypeException($selected, 'string');
    }

    $select_ary = explode(',', $selected);
    $count = 0;

    foreach($select_ary as $sel)
    {
      $trimmed = trim($sel);
      if ($trimmed === '')
      {
        continue;
      }

      if (!is_numeric($trimmed))
      {
        throw new UnexpectedTypeException($trimmed, 'int');
      }
      $count++;
    }

    if (!$count)
    {
      $this->context->buildViolation($constraint->message)
        ->addViolation();
    }
  }
}