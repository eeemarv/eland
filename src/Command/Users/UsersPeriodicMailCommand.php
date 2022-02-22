<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class UsersPeriodicMailCommand implements CommandInterface
{
    #[Sequentially([
        new NotNull(),
        new Type('int'),
    ])]
    public $days;

    #[Sequentially([
        new NotNull(),
        new Json(),
    ])]
    public $block_layout;

    #[Sequentially([
        new NotNull(),
        new Json(),
    ])]
    public $block_select_options;
}
