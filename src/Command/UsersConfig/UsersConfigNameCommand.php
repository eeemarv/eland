<?php declare(strict_types=1);

namespace App\Command\UsersConfig;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class UsersConfigNameCommand implements CommandInterface
{
    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'users.fields.username.self_edit')]
    public $self_edit;
}
