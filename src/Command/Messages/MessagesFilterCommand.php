<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;

class MessagesFilterCommand implements CommandInterface
{
    #[Type(type: 'string')]
    public $q;

    public $cat;

    #[Choice(choices: ['offer', 'want'], multiple: true)]
    public $ow;

    #[Choice(choices: ['srvc', 'stff'], multiple: true)]
    public $srvc;

    #[Choice(choices: ['valid', 'expired'], multiple: true)]
    public $ve;

    #[Choice(choices: ['admin', 'user', 'guest'], multiple: true)]
    public $access;

    #[Choice(choices: ['active', 'new', 'leaving'], multiple: true)]
    public $us;

    #[Type(type: 'int')]
    public $user;

    public $uid;
}
