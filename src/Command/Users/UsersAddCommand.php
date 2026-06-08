<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use App\Validator\User\UniqueUserName;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

#[UniqueUserName(groups:['unique'])]
#[GroupSequence(groups:['UsersNameCommand', 'unique'])]
class UsersAddCommand Implements CommandInterface
{
  #[Sequentially(constraints: [
    new Type(type: 'string'),
    new Length(max: 40),
    new NotNull(),
    new NotBlank(),
  ])]
  public mixed $name;

  #[Email()]
  #[NotNull()]
  public mixed $email;
}
