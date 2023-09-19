<?php declare(strict_types=1);

namespace App\Validator\Mollie;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class IsMollieApikeyValidator extends ConstraintValidator
{
    public function validate($apikey, Constraint $constraint):void
    {
        if (!$constraint instanceof IsMollieApikey)
        {
            throw new UnexpectedTypeException($constraint, CategoryIsLeaf::class);
        }

        if (!isset($apikey) || $apikey === '')
        {
            return;
        }

        if (!(str_starts_with($apikey, 'live_')
            || str_starts_with($apikey, 'test_')))
        {
            $this->context->buildViolation('mollie.not_live_nor_test')
                ->addViolation();
            return;
        }
    }
}