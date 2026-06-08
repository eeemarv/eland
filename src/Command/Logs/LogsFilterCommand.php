<?php declare(strict_types=1);

namespace App\Command\Logs;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class LogsFilterCommand implements CommandInterface
{
  #[Type(type: 'string')]
  public mixed $q;

  #[Type(type: 'string')]
  public mixed $type;

  #[Type(type: 'int')]
  public mixed $user;
}
