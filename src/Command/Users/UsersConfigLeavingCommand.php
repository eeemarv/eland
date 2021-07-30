<?php declare(strict_types=1);

namespace App\Command\Config;

use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;

class UsersConfigLeavingCommand
{
    #[Type('int')]
    public $equilibrium;

    #[Type('bool')]
    public $transactions_enabled;

    #[Type('bool')]
    public $auto_deactivate;

    #[Choice(['admin', 'user', 'guest'])]
    public $access;

    #[Choice(['admin', 'user', 'guest'])]
    public $access_list;

    #[Choice(['admin', 'user', 'guest'])]
    public $access_pane;
}
