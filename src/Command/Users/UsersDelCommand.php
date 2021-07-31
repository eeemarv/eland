<?php declare(strict_types=1);

namespace App\Command\Users;

use Symfony\Component\Validator\Constraints\IsTrue;

class UsersDelCommand
{
    #[IsTrue()]
    public $verify;
}
