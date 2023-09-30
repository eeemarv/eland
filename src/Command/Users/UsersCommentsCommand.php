<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Type;

class UsersCommentsCommand Implements CommandInterface
{
    #[Type(type: 'string')]
    #[Length(max: 50000)]
    public $comments;
}
