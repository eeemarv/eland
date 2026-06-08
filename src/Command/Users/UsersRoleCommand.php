<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use App\Validator\User\UserRole;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class UsersRoleCommand Implements CommandInterface
{
  #[Type(type: 'string')]
  #[NotNull()]
  #[UserRole()]
  public mixed $role;
}
