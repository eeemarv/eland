<?php declare(strict_types=1);

namespace App\Command\Contacts;

use App\Command\CommandInterface;
use App\Validator\Access\Access;
use App\Validator\BulkSelect\BulkSelectNotEmpty;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class ContactsBulkAccessCommand implements CommandInterface
{
  #[BulkSelectNotEmpty(message: 'bulk_select.not_empty.users')]
  public mixed $selected;

  #[Sequentially(constraints: [
    new NotNull(),
    new Access(),
  ])]
  public mixed $access;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public mixed $verify;
}
