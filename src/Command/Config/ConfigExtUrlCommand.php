<?php declare(strict_types=1);

namespace App\Command\Config;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Url;

class ConfigExtUrlCommand implements CommandInterface
{
    #[Url()]
    #[ConfigMap(type: 'str', key: 'system.website_url')]
    public $url;
}
