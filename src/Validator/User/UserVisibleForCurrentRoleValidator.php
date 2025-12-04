<?php declare(strict_types=1);

namespace App\Validator\User;

use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;

// review
class UserVisibleForCurrentRoleValidator extends ConstraintValidator
{
  public function __construct(
    private readonly UserRepository $user_repository,
    private readonly PageParamsService $pp,
  )
  {
  }

  public function validate($user_id, Constraint $constraint):void
  {
    if (!$constraint instanceof UserVisibleForCurrentRole)
    {
      throw new UnexpectedTypeException($constraint, UserVisibleForCurrentRole::class);
    }

    if (!isset($user_id) || !$user_id)
    {
      return;
    }

    $filter_options = [
      'options' => ['min_range' => 1],
    ];

    if (!filter_var($user_id, FILTER_VALIDATE_INT, $filter_options))
    {
      throw new UnexpectedTypeException($user_id, 'number');
    }

    $user = $this->user_repository->get(
      id: $user_id,
      schema: $this->pp->schema_o(),
    );

    if (in_array($user['status'], [1, 2, 7]))
    {
      return;
    }

    if (!$this->pp->is_admin())
    {
      $this->context->buildViolation('user.no_access')
        ->addViolation();
      return;
    }
  }
}