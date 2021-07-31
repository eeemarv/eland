<?php declare(strict_types=1);

namespace App\Command\Users;

use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;

class UsersConfigNewCommand
{
    #[Type('int')]
    public $days;

    #[Choice(['admin', 'user', 'guest'])]
    public $access;

    #[Choice(['admin', 'user', 'guest'])]
    public $access_list;

    #[Choice(['admin', 'user', 'guest'])]
    public $access_pane;
}
