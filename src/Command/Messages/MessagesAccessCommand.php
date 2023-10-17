<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class MessagesAccessCommand implements CommandInterface
{
    #[Sequentially([
        new NotBlank(),
        new Choice(['admin', 'user', 'guest']),
    ])]
    public $access;
}
