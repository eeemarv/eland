<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class UsersConfigNewCommand implements CommandInterface
{
    #[Type(type: 'int')]
    #[ConfigMap(type: 'int', key: 'users.new.days')]
    public $days;

    #[Sequentially(constraints: [
        new NotNull(),
        new Choice(['admin', 'user', 'guest']),
    ])]
    #[ConfigMap(type: 'str', key: 'users.new.access')]
    public $access;

    #[Sequentially(constraints: [
        new NotNull(),
        new Choice(['admin', 'user', 'guest']),
    ])]
    #[ConfigMap(type: 'str', key: 'users.new.access_list')]
    public $access_list;

    #[Sequentially(constraints: [
        new NotNull(),
        new Choice(['admin', 'user', 'guest']),
    ])]
    #[ConfigMap(type: 'str', key: 'users.new.access_pane')]
    public $access_pane;
}
