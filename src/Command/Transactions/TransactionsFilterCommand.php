<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;

class TransactionsFilterCommand implements CommandInterface
{
  #[Type(type: 'string')]
  public mixed $q;

  #[Type(type: 'int')]
  public mixed $from_account;

  #[Choice(choices: ['and', 'or', 'nor'])]
  public mixed $account_logic;

  #[Type(type: 'int')]
  public mixed $to_account;

  #[Type(type: 'string')]
  public mixed $from_date;

  #[Type(type: 'string')]
  public mixed $to_date;

  #[Choice(choices:['srvc', 'stff', 'null'], multiple: true)]
  public mixed $srvc;
}
