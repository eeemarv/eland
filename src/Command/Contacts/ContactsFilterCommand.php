<?php declare(strict_types=1);

namespace App\Command\Contacts;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class ContactsFilterCommand implements CommandInterface
{
    #[Type('string')]
    public $q;

    #[Type('int')]
    public $type;

    #[Sequentially([
        new Type('string'),
        new Choice(['active', 'new', 'leaving', 'inactive', 'ip', 'im', 'extern']),
    ])]
    public $ustatus;

    #[Type('int')]
    public $user;

    #[Choice(choices: ['admin', 'user', 'guest'], multiple: true)]
    public $access;
}
