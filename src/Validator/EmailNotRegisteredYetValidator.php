<?php declare(strict_types=1);

namespace App\Validator;

use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;

class EmailNotRegisteredYetValidator extends ConstraintValidator
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

    public function validate($email, Constraint $constraint)
    {
        if (!$constraint instanceof EmailNotRegisteredYet)
        {
            throw new UnexpectedTypeException($constraint, EmailNotRegisteredYet::class);
        }

        if (!is_string($email))
        {
            throw new UnexpectedTypeException($email, 'string');
        }

        $email_lowercase = strtolower($email);

        $email_count = $this->user_repository->count_email($email_lowercase, $this->pp->schema());

        if ($email_count > 0)
        {
            $this->context->buildViolation('email_not_registered_yet.already_registered')
                ->addViolation();
            return;
        }
    }
}