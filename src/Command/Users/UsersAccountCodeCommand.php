<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use App\Validator\Account\UniqueAccountCode;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

#[UniqueAccountCode()]
class UsersAccountCodeCommand Implements CommandInterface
{
    #[Type(type: 'int')]
    public $user_id;

    #[Sequentially(constraints: [
        new Type(type: 'string'),
        new Length(max: 20),
        new NotNull(),
        new NotBlank(),
    ])]
    public $code;
}
