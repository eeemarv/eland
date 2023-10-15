<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;

class MessagesExpiresAtCommand implements CommandInterface
{
    #[Sequentially([
        new NotBlank(),
        new NotNull(),
        new DateTime(),
    ])]
    public $expires_at;
}
