<?php declare(strict_types=1);

namespace App\Validator\StartsWith;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class StartsWithValidator extends ConstraintValidator
{
  public function validate(
    mixed $content,
    Constraint $constraint
  ):void
  {
    if (!$constraint instanceof StartsWith)
    {
      throw new UnexpectedTypeException($constraint, StartsWith::class);
    }

    if ($content === null)
    {
      return;
    }

    if (!is_string($content))
    {
      throw new UnexpectedTypeException($content, 'string');
    }

    if ($constraint->prefix === '')
    {
      return;
    }

    if (!(str_starts_with($content, $constraint->prefix)))
    {
      $this->context->buildViolation($constraint->message)
        ->setParameter('prefix', $constraint->prefix)
        ->setParameter('content', $content)
        ->addViolation();
    }
  }
}