<?php declare(strict_types=1);

namespace App\Validator\ContactType;

use App\Command\ContactTypes\ContactTypesCommand;
use App\Repository\ContactRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;
use UnexpectedValueException;

class UniqueContactTypeValidator extends ConstraintValidator
{
    public function __construct(
        protected ContactRepository $contact_repository,
        protected PageParamsService $pp
    )
    {
    }

    public function validate($command, Constraint $constraint):void
    {
        if (!$constraint instanceof UniqueContactType)
        {
            throw new UnexpectedTypeException($constraint, UniqueContactType::class);
        }

        if (!$command instanceof ContactTypesCommand)
        {
            throw new UnexpectedTypeException($command, ContactTypesCommand::class);
        }

        if (!isset($constraint->properties) || !$constraint->properties)
        {
            throw new UnexpectedValueException('properties for the ' . UniqueContactType::class . '  needs to be set');
        }

        if (!is_array($constraint->properties))
        {
            throw new UnexpectedTypeException($constraint->properties, 'array');
        }

        $id = $command->id;
        $properties = $constraint->properties;
        $contact_types = $this->contact_repository->get_all_contact_types($this->pp->schema());

        foreach ($properties as $prop)
        {
            if (!isset($command->$prop))
            {
                continue;
            }

            $value_lowercase = strtolower($command->$prop);

            foreach ($contact_types as $row)
            {
                $compare_abbrev = strtolower($row['abbrev']);
                $compare_name = strtolower($row['name']);

                if (in_array($value_lowercase, [$compare_name, $compare_abbrev])
                    && (!isset($id) || $id !== $row['id']))
                {
                    $this->context->buildViolation('contact_types.not_unique')
                        ->atPath($prop)
                        ->addViolation();
                }
            }
        }
    }
}