<?php declare(strict_types=1);

namespace App\Command\Config;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

use function Aws\constantly;

class ConfigNameCommand implements CommandInterface
{
    #[Sequentially(constraints: [
        new NotBlank(),
        new Length(max: 60),
    ])]
    #[ConfigMap(type: 'str', key: 'system.name')]
    public $system_name;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'home.header.enabled')]
    public $home_header_enabled;
}
