<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class MessagesModulesCommand implements CommandInterface
{
    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'messages.fields.service_stuff.enabled')]
    public $service_stuff_enabled;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'messages.fields.category.enabled')]
    public $category_enabled;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'messages.fields.expires_at.enabled')]
    public $expires_at_enabled;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'messages.fields.units.enabled')]
    public $units_enabled;
}
