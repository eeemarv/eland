<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class UsersConfigColumnsCommand implements CommandInterface
{
    #[Type(type: 'int')]
    #[ConfigMap(type: 'int', key: 'accounts.equilibrium')]
    public $equilibrium;

    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'users.leaving.auto_deactivate')]
    public $auto_deactivate;

    #[Sequentially(constraints: [
        new NotNull(),
        new Choice(['admin', 'user', 'guest']),
    ])]
    #[ConfigMap(type: 'str', key: 'users.leaving.access')]
    public $access;

    #[Sequentially(constraints: [
        new NotNull(),
        new Choice(['admin', 'user', 'guest']),
    ])]
    #[ConfigMap(type: 'str', key: 'users.leaving.access_pane')]
    public $access_list;

    #[Sequentially(constraints: [
        new NotNull(),
        new Choice(['admin', 'user', 'guest']),
    ])]
    #[ConfigMap(type: 'str', key: 'users.leaving.access_list')]
    public $access_pane;
}
