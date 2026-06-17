<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Type;

class TransactionsMassManyToOneCommand implements CommandInterface
{
  #[All([
    new PositiveOrZero(),
  ])]
  public array $amounts = [];

  #[NotNull()]
  #[Type(type: 'int')]
  public mixed $to_account_id;

  #[NotNull()]
  #[Type(type: 'string')]
  #[Length(min: 1, max: 60)]
  public mixed $description;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public mixed $verify;
}
