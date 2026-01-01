<?php declare(strict_types=1);

namespace App\Command\UsersBulk;

use App\Command\CommandInterface;
use App\Validator\BulkSelect\BulkSelectNotEmpty;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class UsersBulkPeriodicOverviewEnCommand implements CommandInterface
{
  #[BulkSelectNotEmpty(message: 'bulk_select.not_empty.users')]
  public $selected;

  #[Type(type: 'bool')]
  #[NotNull()]
  public $periodic_overview_en;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public $verify;
}
