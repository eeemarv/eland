<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class UsersAccountLimitsCommand Implements CommandInterface
{
    #[Type(type: 'int')]
    public $min_limit;

    #[Type(type: 'int')]
    public $max_limit;
}
