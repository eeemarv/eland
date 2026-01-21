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

  public function validate($code, Constraint $constraint):void
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

    if (!preg_match('/^[A-Za-z0-9-]+$/D', $code))
    {
      $this->context->buildViolation('users_account_edit.code_chars_not_allowed')
        ->atPath('code')
        ->addViolation();
      return;
    }
  }
}