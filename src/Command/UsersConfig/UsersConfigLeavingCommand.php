<?php declare(strict_types=1);

namespace App\Command\UsersConfig;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class UsersConfigLeavingCommand implements CommandInterface
{
  #[Type(type: 'int')]
  #[ConfigMap(type: 'int', key: 'accounts.equilibrium')]
  public mixed $equilibrium;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'users.leaving.auto_deactivate')]
  public mixed $auto_deactivate;

  #[Sequentially(constraints: [
    new NotNull(),
    new Choice(['admin', 'user', 'guest']),
  ])]
  #[ConfigMap(type: 'str', key: 'users.leaving.access')]
  public mixed $access;

  #[Sequentially(constraints: [
    new NotNull(),
    new Choice(['admin', 'user', 'guest']),
  ])]
  #[ConfigMap(type: 'str', key: 'users.leaving.access_pane')]
  public mixed $access_list;

  #[Sequentially(constraints: [
    new NotNull(),
    new Choice(['admin', 'user', 'guest']),
  ])]
  #[ConfigMap(type: 'str', key: 'users.leaving.access_list')]
  public mixed $access_pane;
}
