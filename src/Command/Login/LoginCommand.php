<?php declare(strict_types=1);

namespace App\Command\Login;

use App\Command\CommandInterface;
use App\Validator\Login\Login;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Sequentially;

#[Login(groups: ['login'])]
#[GroupSequence(groups: ['LoginCommand', 'login'])]
class LoginCommand implements CommandInterface
{
  #[Sequentially(constraints: [
    new NotBlank(),
    new Length(min: 2, max: 100),
  ])]
  public mixed $login;

  #[Sequentially(constraints: [
    new NotBlank(),
    new Length(min: 5, max: 100),
  ])]
  public mixed $password;

  public mixed $is_master;
  public mixed $id;
  public mixed $password_hashing_updated;
}
