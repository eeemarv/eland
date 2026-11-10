<?php declare(strict_types=1);

namespace App\Validator\Account;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class AccountCodeCharsValidator extends ConstraintValidator
{
  public function __construct(
  )
  {
  }

  public function validate(mixed $code, Constraint $constraint):void
  {
    if (!$constraint instanceof AccountCodeChars)
    {
      throw new UnexpectedTypeException($constraint, AccountCodeChars::class);
    }

    if (!isset($code))
    {
      return;
    }

    if (!is_string($code))
    {
      throw new UnexpectedTypeException($code, 'string');
    }

    /**
     * A hyphen can not be at the beginning or
     * end or next to another hyphen
     */
    if (preg_match('/(^-|-$|--)/', $code)) 
    {
      $this->context
        ->buildViolation('account_code.hyphen_should_be_between')
        ->addViolation();
    }

    if (!preg_match('/^[A-Za-z0-9-]+$/D', $code))
    {
      $this->context->buildViolation('account_code.chars_not_allowed')
        ->addViolation();
      return;
    }
  }
}