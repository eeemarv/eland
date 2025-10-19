<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Type;

class UsersDelCommand implements CommandInterface
{
  #[IsTrue()]
  #[Type(type: 'bool')]
  public $verify;
}
