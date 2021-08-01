<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class UsersPeriodicMailCommand implements CommandInterface
{
    #[Type('int')]
    public $days;

    #[Type('string')]
    public $block_layout;

    #[Type('string')]
    public $block_select_options;
}
