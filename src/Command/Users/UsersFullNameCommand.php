<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class UsersFullNameCommand implements CommandInterface
{
    #[Type('bool')]
    public $self_edit;
}
