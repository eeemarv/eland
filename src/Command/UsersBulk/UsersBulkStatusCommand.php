<?php declare(strict_types=1);

namespace App\Command\UsersBulk;

use App\Command\CommandInterface;
use App\Validator\BulkSelect\BulkSelectNotEmpty;
use App\Validator\User\UserStatus;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class UsersBulkStatusCommand implements CommandInterface
{
  #[BulkSelectNotEmpty(message: 'bulk_select.not_empty.users')]
  public mixed $selected;

  #[NotNull()]
  #[Type(type: 'int')]
  #[UserStatus()]
  public mixed $status;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public mixed $verify;
}
