<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class TransactionsEditServiceStuffCommand implements CommandInterface
{
  // disabled
  public mixed $from_account_id;
  // disabled
  public mixed $to_account_id;
  // disabled
  public mixed $amount;
  // disabled 
  public mixed $description;

  #[NotNull()]
  #[Choice(choices: ['service', 'stuff', 'null_service_stuff'])]
  public mixed $service_stuff;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public mixed $verify;
}
