<?php declare(strict_types=1);

namespace App\Command\UsersBulk;

use App\Command\CommandInterface;
use App\Validator\Access\Access;
use App\Validator\BulkSelect\BulkSelectNotEmpty;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class UsersBulkFullNameAccessCommand implements CommandInterface
{
  #[BulkSelectNotEmpty(message: 'bulk_select.not_empty.users')]
  public mixed $selected;

  #[NotNull()]
  #[Access()]
  public mixed $access;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public mixed $verify;
}
