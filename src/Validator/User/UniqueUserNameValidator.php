<?php declare(strict_types=1);

namespace App\Validator\User;

use App\Command\Users\UsersNameCommand;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;

class UniqueUserNameValidator extends ConstraintValidator
{
    public function __construct(
        protected UserRepository $user_repository,
        protected PageParamsService $pp,
    )
    {
    }

    public function validate($command, Constraint $constraint):void
    {
        if (!$constraint instanceof UniqueUserName)
        {
            throw new UnexpectedTypeException($constraint, UniqueUserName::class);
        }

        if (!$command instanceof UsersNameCommand)
        {
            throw new UnexpectedTypeException($command, UsersNameCommand::class);
        }

        $name = $command->name;
        $id = $command->id;

        $is_unique = $this->user_repository->is_unique_name($name, $id, $this->pp->schema());

        if (!$is_unique)
        {
            $this->context->buildViolation('users_name_edit.name_not_unique')
                ->atPath('name')
                ->addViolation();
            return;
        }
    }
}