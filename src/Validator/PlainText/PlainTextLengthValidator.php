<?php declare(strict_types=1);

namespace App\Validator\PlainText;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PlainTextLengthValidator extends ConstraintValidator
{
  public function validate($content, Constraint $constraint):void
  {
    if (!$constraint instanceof PlainTextLength)
    {
      throw new UnexpectedTypeException($constraint, PlainTextLength::class);
    }

    if ($content === null)
    {
      return;
    }

    if (!is_string($content))
    {
      throw new UnexpectedTypeException($content, 'string');
    }

    $plain = trim(strip_tags($content));
    $len = mb_strlen($plain);

    if ($len < $constraint->min) {
      $this->context->buildViolation($constraint->message_min)
        ->setParameter('min', (string) $constraint->min)
        ->setParameter('len', (string) $len)
        ->addViolation();
    }

    if ($len > $constraint->max) {
      $this->context->buildViolation($constraint->message_max)
        ->setParameter('max', (string) $constraint->max)
        ->setParameter('len', (string) $len)
        ->addViolation();
    }
  }
}