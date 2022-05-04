<?php declare(strict_types=1);

namespace App\Command\Contacts;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class ContactsFilterCommand implements CommandInterface
{
    #[Type('string', groups: ['filter'])]
    public $q;

    #[Type('int', groups: ['filter'])]
    public $type;

    #[Sequentially([
        new Type('string', groups: ['filter']),
        new Choice(['active', 'new', 'leaving', 'inactive', 'ip', 'im', 'extern'], groups: ['filter']),
    ])]
    public $ustatus;

    #[Type('int', groups: ['filter'])]
    public $user;

    #[Choice(choices: ['admin', 'user', 'guest'], multiple: true, groups: ['filter'])]
    public $access;
}
