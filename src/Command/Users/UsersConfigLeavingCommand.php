<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;

class UsersConfigLeavingCommand implements CommandInterface
{
    #[Type('int')]
    #[ConfigMap(type: 'int', key: 'accounts.equilibrium')]
    public $equilibrium;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'users.leaving.auto_deactivate')]
    public $auto_deactivate;

    #[Choice(['admin', 'user', 'guest'])]
    #[ConfigMap(type: 'str', key: 'users.leaving.access')]
    public $access;

    #[Choice(['admin', 'user', 'guest'])]
    #[ConfigMap(type: 'str', key: 'users.leaving.access_pane')]
    public $access_list;

    #[Choice(['admin', 'user', 'guest'])]
    #[ConfigMap(type: 'str', key: 'users.leaving.access_list')]
    public $access_pane;
}