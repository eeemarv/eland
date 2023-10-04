<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Date;

class UsersBirthdateCommand Implements CommandInterface
{
    #[Date()]
    public $birthdate;
}
