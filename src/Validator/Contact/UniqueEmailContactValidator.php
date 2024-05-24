<?php declare(strict_types=1);

namespace App\Validator\Contact;

use App\Command\Contacts\ContactsCommand;
use App\Repository\ContactRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UniqueEmailContactValidator extends ConstraintValidator
{
    public function __construct(
        protected ContactRepository $contact_repository,
        protected PageParamsService $pp,
        protected ValidatorInterface $validator
    )
    {
    }

    public function validate($command, Constraint $constraint):void
    {
        if (!$constraint instanceof UniqueEmailContact)
        {
            throw new UnexpectedTypeException($constraint, UniqueEmailContact::class);
        }

        if (!$command instanceof ContactsCommand)
        {
            throw new UnexpectedTypeException($command, ContactsCommand::class);
        }

        $mail_contact_type = $this->contact_repository->get_contact_type_by_abbrev('mail', $this->pp->schema());

        if ($command->contact_type_id !== $mail_contact_type['id'])
        {
            return;
        }

        $email = $command->value;
        $email_constraint = new Email();

        $errors = $this->validator->validate($email, $email_constraint);

        foreach ($errors as $error)
        {
            $this->context->buildViolation($error->getMessage())
                ->atPath('value')
                ->addViolation();
        }

        if (count($errors))
        {
            return;
        }

        if ($this->pp->is_admin())
        {
            return;
        }

        $user_id = $command->user_id ?? 0;

        $count = $this->contact_repository->get_mail_count_except_for_user($email, $user_id, $this->pp->schema());

        if ($count)
        {
            $this->context->buildViolation('email.not_unique_for_active_users')
                ->atPath('value')
                ->addViolation();
        }
    }
}