<?php declare(strict_types=1);

namespace App\Validator\Account;

use App\Command\Users\UsersAccountCodeCommand;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;

class UniqueAccountCodeValidator extends ConstraintValidator
{
    public function __construct(
        protected UserRepository $user_repository,
        protected PageParamsService $pp,
    )
    {
    }

    public function validate($command, Constraint $constraint):void
    {
        if (!$constraint instanceof UniqueAccountCode)
        {
            throw new UnexpectedTypeException($constraint, Login::class);
        }

        if (!$command instanceof UsersAccountCodeCommand)
        {
            throw new UnexpectedTypeException($command, UsersAccountCodeCommand::class);
        }

        $code = $command->code;
        $user_id = $command->user_id;

        $is_unique = $this->user_repository->is_unique_code($code, $user_id, $this->pp->schema());

        if (!$is_unique)
        {
            $this->context->buildViolation('users_account_edit.code_not_unique')
                ->atPath('code')
                ->addViolation();
            return;
        }
    }
}