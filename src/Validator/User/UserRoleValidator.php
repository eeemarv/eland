<?php declare(strict_types=1);

namespace App\Validator\User;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UserRoleValidator extends ConstraintValidator
{
  public function __construct(
  )
  {
  }

  public function validate(
    mixed $role,
    Constraint $constraint,
  ):void
  {
    if (!$constraint instanceof UserRole)
    {
      throw new UnexpectedTypeException($constraint, UserRole::class);
    }

    if (!isset($role))
    {
      return;
    }

    if (!is_string($role))
    {
      throw new UnexpectedTypeException($role, 'string');
    }

    if (!in_array($role, ['admin', 'user']))
    {
      $this->context->buildViolation('user.role')
        ->addViolation();
      return;
    }
  }
}