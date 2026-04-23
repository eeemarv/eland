<?php declare(strict_types=1);

namespace App\Validator\User;

use App\Service\ConfigService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;

class UserRoleValidator extends ConstraintValidator
{
  public function __construct(
    private readonly ConfigService $config_service,
    private readonly PageParamsService $pp,
  )
  {
  }

  public function validate($role, Constraint $constraint):void
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