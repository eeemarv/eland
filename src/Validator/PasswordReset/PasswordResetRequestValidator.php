<?php declare(strict_types=1);

namespace App\Validator\PasswordReset;


use App\Command\PasswordReset\PasswordResetRequestCommand;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;

class PasswordResetRequestValidator extends ConstraintValidator
{
    protected UserRepository $user_repository;
    protected PageParamsService $pp;

    public function __construct(
        UserRepository $user_repository,
        PageParamsService $pp
    )
    {
        $this->user_repository = $user_repository;
        $this->pp = $pp;
    }

    public function validate($password_reset_request_command, Constraint $constraint)
    {
        if (!$constraint instanceof PasswordResetRequest)
        {
            throw new UnexpectedTypeException($constraint, PasswordResetRequest::class);
        }

        if (!$password_reset_request_command instanceof PasswordResetRequestCommand)
        {
            throw new UnexpectedTypeException($password_reset_request_command, PasswordResetRequestCommand::class);
        }

        $email_lowercase = strtolower($password_reset_request_command->email);

        $count_by_email = $this->user_repository->count_active_by_email($email_lowercase, $this->pp->schema());

        if ($count_by_email > 1)
        {
            $this->context->buildViolation('password_reset_request.email.not_unique')
                ->atPath('email')
                ->addViolation();
            return;
        }

        if ($count_by_email === 0)
        {
            $this->context->buildViolation('password_reset_request.email.not_known')
                ->atPath('email')
                ->addViolation();
            return;
        }
    }
}