<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class UsersPeriodicOverviewCommand Implements CommandInterface
{
    #[Type(type: 'bool')]
    #[NotNull()]
    public $enabled;
}
