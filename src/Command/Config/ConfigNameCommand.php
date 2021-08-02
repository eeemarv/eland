<?php declare(strict_types=1);

namespace App\Command\Config;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ConfigNameCommand implements CommandInterface
{
    #[NotBlank()]
    #[Length(max: 60)]
    #[ConfigMap(type: 'str', key: 'system.name')]
    public $system_name;
}
