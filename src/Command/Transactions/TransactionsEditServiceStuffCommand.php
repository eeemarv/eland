<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class TransactionsEditServiceStuffCommand implements CommandInterface
{
  #[NotNull()]
  #[Choice(choices: ['service', 'stuff'])]
  public mixed $service_stuff;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public mixed $verify;
}
