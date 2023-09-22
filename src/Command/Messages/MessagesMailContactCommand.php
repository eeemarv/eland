<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class MessagesMailContactCommand implements CommandInterface
{
    #[Sequentially(constraints: [
        new NotBlank(),
        new Length(min: 10, max: 10000),
    ])]
    public $message;

    #[Type(type: 'bool')]
    public $cc;
}
