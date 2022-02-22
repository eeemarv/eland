<?php declare(strict_types=1);

namespace App\Command\SendMessage;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class SendMessageCCCommand implements CommandInterface
{
    #[Sequentially([
        new NotBlank(),
        new Length(min: 10, max: 10000),
    ])]
    public $message;

    #[Type('bool')]
    public $cc;
}
