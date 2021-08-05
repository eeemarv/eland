<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;

class UsersConfigNewCommand implements CommandInterface
{
    #[Type('int')]
    #[ConfigMap(type: 'int', key: 'users.new.days')]
    public $days;

    #[Choice(['admin', 'user', 'guest'])]
    #[ConfigMap(type: 'str', key: 'users.new.access')]
    public $access;

    #[Choice(['admin', 'user', 'guest'])]
    #[ConfigMap(type: 'str', key: 'users.new.access_list')]
    public $access_list;

    #[Choice(['admin', 'user', 'guest'])]
    #[ConfigMap(type: 'str', key: 'users.new.access_pane')]
    public $access_pane;
}
