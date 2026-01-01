<?php declare(strict_types=1);

namespace App\Command\UsersBulk;

use App\Command\CommandInterface;
use App\Validator\BulkSelect\BulkSelectNotEmpty;
use App\Validator\User\UserRole;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class UsersBulkRoleCommand implements CommandInterface
{
  #[BulkSelectNotEmpty(message: 'bulk_select.not_empty.users')]
  public $selected;

  #[NotNull()]
  #[Type(type: 'string')]
  #[UserRole()]
  public $role;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public $verify;
}
