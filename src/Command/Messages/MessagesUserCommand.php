<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Command\CommandInterface;
use App\Validator\User\ActiveUser;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class MessagesUserCommand implements CommandInterface
{
    #[Sequentially(constraints: [
        new NotBlank(),
        new ActiveUser(),
    ])]
    public $user_id;
}
