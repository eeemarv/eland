<?php declare(strict_types=1);

namespace App\Command\Contacts;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class ContactsFilterCommand implements CommandInterface
{
    #[Type(type: 'string', groups: ['filter'])]
    public $q;

    #[Type(type: 'int', groups: ['filter'])]
    public $type;

    #[Sequentially(constraints: [
        new Type(type: 'string', groups: ['filter']),
        new Choice(choices: ['active', 'new', 'leaving', 'inactive', 'ip', 'im', 'extern'], groups: ['filter']),
    ])]
    public $ustatus;

    #[Type(type: 'int', groups: ['filter'])]
    public $user;

    #[Choice(choices: ['admin', 'user', 'guest'], multiple: true, groups: ['filter'])]
    public $access;
}
