<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Command\CommandInterface;
use App\Validator\BulkSelect\BulkSelectNotEmpty;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class TransactionsManyToOneCommand implements CommandInterface
{
  #[BulkSelectNotEmpty(message: 'bulk_select.not_empty.users')]
  public mixed $from_accounts_amounts;

  #[NotNull()]
  #[Type(type: 'bool')]
  public mixed $is_leaving;

  #[NotNull()]
  #[Type(type: 'string')]
  #[Length(min: 1, max: 60)]
  public mixed $description;

  #[NotNull()]
  #[Type(type: 'int')]
  public mixed $to_account;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public mixed $verify;
}
