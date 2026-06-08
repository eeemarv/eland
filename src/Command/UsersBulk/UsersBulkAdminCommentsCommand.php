<?php declare(strict_types=1);

namespace App\Command\UsersBulk;

use App\Command\CommandInterface;
use App\Validator\BulkSelect\BulkSelectNotEmpty;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Type;

class UsersBulkAdminCommentsCommand implements CommandInterface
{
  #[BulkSelectNotEmpty(message: 'bulk_select.not_empty.users')]
  public mixed $selected;

  #[Type(type: 'string')]
  #[Length(max: 200)]
  public mixed $admin_comments;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public mixed $verify;
}
