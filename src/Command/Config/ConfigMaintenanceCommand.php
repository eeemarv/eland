<?php declare(strict_types=1);

namespace App\Command\Config;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class ConfigMaintenanceCommand implements CommandInterface
{
    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'system.maintenance_en')]
    public $maintenance_en;
}
