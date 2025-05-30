<?php declare(strict_types=1);

namespace App\Validator\Email;

use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;

class EmailUniqueToActiveUserValidator extends ConstraintValidator
{
    public function __construct(
        protected UserRepository $user_repository,
        protected PageParamsService $pp
    )
    {
    }

    public function validate($email, Constraint $constraint):void
    {
        if (!$constraint instanceof EmailUniqueToActiveUser)
        {
            throw new UnexpectedTypeException($constraint, EmailUniqueToActiveUser::class);
        }

        if (!is_string($email))
        {
            throw new UnexpectedTypeException($email, 'string');
        }

        $email_lowercase = strtolower($email);

        $count_by_email = $this->user_repository->count_active_by_email($email_lowercase, $this->pp->schema());

        if ($count_by_email > 1)
        {
            $this->context->buildViolation('email_unique_to_active_user.not_unique')
                ->addViolation();
            return;
        }

        if ($count_by_email === 0)
        {
            $this->context->buildViolation('email_unique_to_active_user.not_known')
                ->addViolation();
            return;
        }
    }
}