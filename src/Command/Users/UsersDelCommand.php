<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\IsTrue;

class UsersDelCommand implements CommandInterface
{
    #[IsTrue()]
    public $verify;
}
