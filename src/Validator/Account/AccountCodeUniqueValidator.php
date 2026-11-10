<?php declare(strict_types=1);

namespace App\Validator\Account;

use App\Command\Users\UsersAccountCodeCommand;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;

class AccountCodeUniqueValidator extends ConstraintValidator
{
  public function __construct(
    private readonly UserRepository $user_repository,
    private readonly PageParamsService $pp,
  )
  {
  }

  public function validate(
    mixed $command,
    Constraint $constraint,
  ):void
  {
    if (!$constraint instanceof AccountCodeUnique)
    {
      throw new UnexpectedTypeException($constraint, AccountCodeUnique::class);
    }

    if (!$command instanceof UsersAccountCodeCommand)
    {
      throw new UnexpectedTypeException($command, UsersAccountCodeCommand::class);
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
      $this->context->buildViolation('account_code.not_unique')
        ->atPath('code')
        ->addViolation();
      return;
    }
  }
}