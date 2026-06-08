<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use App\Validator\Password\PasswordStrength;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;

class UsersPasswordEditCommand Implements CommandInterface
{
  #[Sequentially(constraints: [
    new NotNull(groups: ['user', 'admin']),
    new NotBlank(groups: ['user', 'admin']),
    new Length(min: 5, max: 100, groups: ['user', 'admin']),
    new PasswordStrength(groups: ['user']),
  ])]
  public mixed $password;
}
