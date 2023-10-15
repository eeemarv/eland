<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class MessagesUnitsCommand implements CommandInterface
{
    #[Sequentially([
        new NotBlank(),
        new NotNull(),
        new Type(type: 'int'),
        new PositiveOrZero(),
    ])]
    public $amount;

    #[Sequentially([
        new Length(max: 15),
    ])]
    public $units;
}
