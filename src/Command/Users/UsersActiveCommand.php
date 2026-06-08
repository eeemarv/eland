<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class UsersActiveCommand Implements CommandInterface
{
  #[NotNull()]
  #[Type(type: 'bool')]
  public mixed $is_active;

  #[NotNull()]
  #[Type(type: 'bool')]
  public mixed $send_email;

  #[NotNull()]
  #[Type(type: 'bool')]
  public mixed $send_email_cc;
}
