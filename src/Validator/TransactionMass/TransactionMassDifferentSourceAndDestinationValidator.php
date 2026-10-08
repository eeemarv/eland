<?php declare(strict_types=1);

namespace App\Validator\TransactionMass;

use App\Command\Transactions\TransactionsMassManyToOneCommand;
use App\Command\Transactions\TransactionsMassOneToManyCommand;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class TransactionMassDifferentSourceAndDestinationValidator extends ConstraintValidator
{
  public function __construct(
  )
  {
  }

  public function validate(
    mixed $command,
    Constraint $constraint,
  ):void
  {
    if (!$constraint instanceof TransactionMassDifferentSourceAndDestination)
    {
      throw new UnexpectedTypeException(
        $constraint, TransactionMassDifferentSourceAndDestination::class
      );
    }

    $type = null;

    if ($command instanceof TransactionsMassManyToOneCommand)
    {
      $type = 'many_to_one';
    }

    if ($command instanceof TransactionsMassOneToManyCommand)
    {
      $type = 'one_to_many';
    }

    if (!isset($type))
    {
      throw new UnexpectedTypeException(
        $command,
        TransactionsMassManyToOneCommand::class . ' or ' .
        TransactionsMassOneToManyCommand::class
      );
    }

    $account_property = $type === 'many_to_one' ?  'to_account_id' : 'from_account_id';

    if (!isset($command->$account_property))
    {
      return;
    }

    $account_id = $command->$account_property;

    foreach ($command->amounts as $a_id => $amount)
    {
      if (!isset($amount))
      {
        continue;
      }

      if (!($amount > 0))
      {
        continue;
      }

      if ($a_id !== $account_id)
      {
        continue;
      }

      $this->context->buildViolation('transaction.source_and_destination_same')
        ->atPath($account_property)
        ->addViolation();
    }
  }
}