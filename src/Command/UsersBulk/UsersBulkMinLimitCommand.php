<?php declare(strict_types=1);

namespace App\Command\UsersBulk;

use App\Command\CommandInterface;
use App\Validator\BulkSelect\BulkSelectNotEmpty;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NegativeOrZero;
use Symfony\Component\Validator\Constraints\Type;

class UsersBulkMinLimitCommand implements CommandInterface
{
  #[BulkSelectNotEmpty(message: 'bulk_select.not_empty.users')]
  public mixed $selected;

  #[Type(type: 'int')]
  #[NegativeOrZero()]
  public mixed $min_limit;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public mixed $verify;
}
