<?php declare(strict_types=1);

namespace App\Command\Users;

use Symfony\Component\Validator\Constraints\Type;

class UsersFullNameCommand
{
    #[Type('bool')]
    public $self_edit;
}
