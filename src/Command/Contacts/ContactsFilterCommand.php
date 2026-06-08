<?php declare(strict_types=1);

namespace App\Command\Contacts;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class ContactsFilterCommand implements CommandInterface
{
  #[Type(type: 'string', groups: ['filter'])]
  public mixed $q;

  #[Type(type: 'int', groups: ['filter'])]
  public mixed $type;

  #[Sequentially(constraints: [
    new Type(type: 'string', groups: ['filter']),
    new Choice(choices: ['active', 'new', 'leaving', 'inactive', 'ip', 'im', 'extern'], groups: ['filter']),
  ])]
  public mixed $ustatus;

  #[Type(type: 'int', groups: ['filter'])]
  public mixed $user;

  #[Choice(choices: ['admin', 'user', 'guest'], multiple: true, groups: ['filter'])]
  public mixed $access;
}
