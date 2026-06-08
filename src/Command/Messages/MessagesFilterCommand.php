<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;

class MessagesFilterCommand implements CommandInterface
{
  #[Type(type: 'string')]
  public mixed $q;

  public mixed $cat;

  #[Choice(choices: ['offer', 'want'], multiple: true)]
  public mixed $ow;

  #[Choice(choices: ['srvc', 'stff', 'null'], multiple: true)]
  public mixed $srvc;

  #[Choice(choices: ['valid', 'expired'], multiple: true)]
  public mixed $ve;

  #[Choice(choices: ['admin', 'user', 'guest'], multiple: true)]
  public mixed $access;

  #[Choice(choices: ['active', 'new', 'leaving'], multiple: true)]
  public mixed $us;

  #[Type(type: 'int')]
  public mixed $user;

  public mixed $uid;
}
