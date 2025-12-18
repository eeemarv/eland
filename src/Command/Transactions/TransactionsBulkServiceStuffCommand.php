<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Command\CommandInterface;
use App\Validator\BulkSelect\BulkSelectNotEmpty;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class TransactionsBulkServiceStuffCommand implements CommandInterface
{
  #[BulkSelectNotEmpty(message: 'bulk_select.not_empty.transactions')]
  public $selected;

  #[NotNull()]
  #[Choice(options: ['service', 'stuff', 'null_service_stuff'])]
  public $service_stuff;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public $verify;
}
