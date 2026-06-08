<?php declare(strict_types=1);

namespace App\Command\UsersBulk;

use App\Command\CommandInterface;
use App\Validator\BulkSelect\BulkSelectNotEmpty;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class UsersBulkAccountLeavingCommand implements CommandInterface
{
  #[BulkSelectNotEmpty(message: 'bulk_select.not_empty.users')]
  public mixed $selected;

  #[NotNull()]
  #[Type(type: 'bool')]
  public mixed $is_leaving;

  #[NotNull()]
  #[Type(type: 'bool')]
  public mixed $send_email;

  #[NotNull()]
  #[Type(type: 'bool')]
  public mixed $send_email_cc;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public mixed $verify;
}
