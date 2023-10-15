<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class MessagesContentCommand implements CommandInterface
{
    #[Sequentially([
        new NotBlank(),
        new Length(min: 20, max: 5000),
    ])]
    public $content;
}
