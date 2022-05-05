<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

use function PHPSTORM_META\type;

class UsersFullNameCommand implements CommandInterface
{
    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'users.fields.full_name.self_edit')]
    public $self_edit;
}
