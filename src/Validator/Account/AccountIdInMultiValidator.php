<?php declare(strict_types=1);

namespace App\Validator\Account;

use App\Command\Users\UsersAccountCodeCommand;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;

class AccountIdInMultiValidator extends ConstraintValidator
{
  private array $user_ids;

  public function __construct(
    private readonly UserRepository $user_repository,
    private readonly PageParamsService $pp,
  )
  {
  }

  public function validate(mixed $value, Constraint $constraint):void
  {
    if (!$constraint instanceof AccountIdInMulti)
    {
      throw new UnexpectedTypeException($constraint, AccountIdInMulti::class);
    }

    if (!isset($user_ids))
    {

    }

    $code = $command->code;
    $user_id = $command->user_id;

    $is_unique = $this->user_repository->is_unique_code(
      code: $code,
      except_id: $user_id,
      schema: $this->pp->schema_o(),
    );

    if (!$is_unique)
    {
      $this->context->buildViolation('users_account_edit.code_not_unique')
        ->atPath('code')
        ->addViolation();
      return;
    }
  }
}