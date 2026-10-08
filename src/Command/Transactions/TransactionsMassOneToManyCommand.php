<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Command\CommandInterface;
use App\Validator\TransactionMass\TransactionMassDifferentSourceAndDestination;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Type;

#[TransactionMassDifferentSourceAndDestination()]
class TransactionsMassOneToManyCommand implements CommandInterface
{
  #[NotNull()]
  #[Type(type: 'int')]
  public mixed $from_account_id;

  #[All([
    new Type(type: 'int'),
    new Positive(),
  ])]
  public array $amounts = [];

  #[NotNull()]
  #[Type(type: 'string')]
  #[Length(min: 1, max: 60)]
  public mixed $description;

  #[Choice(['service', 'stuff'])]
  public mixed $service_stuff;

  #[Type(type: 'bool')]
  public mixed $email_notify_en;

  #[Type(type: 'bool')]
  public mixed $email_copy_en;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public mixed $verify;
}
