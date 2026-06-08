<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class UsersAccountLeavingCommand Implements CommandInterface
{
  #[Type(type: 'bool')]
  #[NotNull()]
  public mixed $is_leaving;

  #[NotNull()]
  #[Type(type: 'bool')]
  public mixed $send_email;

  #[NotNull()]
  #[Type(type: 'bool')]
  public mixed $send_email_cc;
}
