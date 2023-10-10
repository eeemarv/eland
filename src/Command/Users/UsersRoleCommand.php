<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class UsersRoleCommand Implements CommandInterface
{
    #[Type(type: 'string')]
    #[NotNull()]
    #[Choice(['admin', 'user'])]
    public $role;
}
