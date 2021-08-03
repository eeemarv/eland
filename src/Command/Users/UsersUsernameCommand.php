<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class UsersUsernameCommand implements CommandInterface
{
    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'users.fields.username.self_edit')]
    public $self_edit;
}
