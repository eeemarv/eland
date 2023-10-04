<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class UsersFullNameCommand Implements CommandInterface
{
    #[Type(type: 'string')]
    #[Length(max: 128)]
    public $full_name;

    #[Sequentially(constraints: [
        new NotNull(),
        new Type('string'),
        new Choice(['admin', 'user', 'guest']),
    ])]
    public $full_name_access;
}
