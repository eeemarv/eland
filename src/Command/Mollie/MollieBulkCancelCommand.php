<?php declare(strict_types=1);

namespace App\Command\Mollie;

use App\Command\CommandInterface;
use App\Validator\BulkSelect\BulkSelectNotEmpty;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Type;

class MollieBulkCancelCommand implements CommandInterface
{
  #[BulkSelectNotEmpty(message: 'bulk_select.not_empty.mollie_payment_requests')]
  public $selected;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public $verify;
}
