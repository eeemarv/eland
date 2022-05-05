<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class UsersPeriodicMailCommand implements CommandInterface
{
    #[Sequentially(constraints: [
        new NotNull(),
        new Type(type: 'int'),
    ])]
    public $days;

    #[Sequentially(constraints: [
        new NotNull(),
        new Json(),
    ])]
    public $block_layout;

    #[Sequentially(constraints: [
        new NotNull(),
        new Json(),
    ])]
    public $block_select_options;
}
