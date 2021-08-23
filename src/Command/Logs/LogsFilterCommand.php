<?php declare(strict_types=1);

namespace App\Command\Logs;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class LogsFilterCommand implements CommandInterface
{
    #[Type('string')]
    public $q;

    #[Type('string')]
    public $type;

    #[Type('int')]
    public $user;
}
