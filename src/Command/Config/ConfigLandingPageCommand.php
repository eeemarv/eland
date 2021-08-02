<?php declare(strict_types=1);

namespace App\Command\Config;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class ConfigLandingPageCommand implements CommandInterface
{
    #[NotNull()]
    #[NotBlank()]
    #[ConfigMap(type: 'str', key: 'system.default_landing_page')]
    public $landing_page;
}
