<?php declare(strict_types=1);

namespace App\Validator\User;

use App\Cnst\StatusCnst;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UserStatusValidator extends ConstraintValidator
{
  public function __construct(
  )
  {
  }

  public function validate(
    mixed $status,
    Constraint $constraint,
  ):void
  {
    if (!$constraint instanceof UserStatus)
    {
      throw new UnexpectedTypeException($constraint, UserStatus::class);
    }

    if (!isset($status))
    {
      return;
    }

    if (!is_int($status))
    {
      throw new UnexpectedTypeException($status, 'int');
    }

    if (!in_array($status, array_keys(StatusCnst::LABEL_ARY)))
    {
      $this->context->buildViolation('user.status')
        ->addViolation();
      return;
    }
  }
}