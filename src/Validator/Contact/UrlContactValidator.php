<?php declare(strict_types=1);

namespace App\Validator\Contact;

use App\Command\Contacts\ContactsCommand;
use App\Repository\ContactRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UrlContactValidator extends ConstraintValidator
{
    public function __construct(
        protected ContactRepository $contact_repository,
        protected PageParamsService $pp,
        protected ValidatorInterface $validator
    )
    {
    }

    public function validate($command, Constraint $constraint)
    {
        if (!$constraint instanceof UrlContact)
        {
            throw new UnexpectedTypeException($constraint, UrlContact::class);
        }

        if (!$command instanceof ContactsCommand)
        {
            throw new UnexpectedTypeException($command, ContactsCommand::class);
        }

        $url_contact_type = $this->contact_repository->get_contact_type_by_abbrev('web', $this->pp->schema());

        if ($command->contact_type_id !== $url_contact_type['id'])
        {
            return;
        }

        $url = $command->value;
        $url_constraint = new Url();

        $errors = $this->validator->validate($url, $url_constraint);

        foreach ($errors as $error)
        {
            $this->context->buildViolation($error->getMessage())
                ->atPath('value')
                ->addViolation();
        }
    }
}