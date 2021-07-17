<?php declare(strict_types=1);

namespace App\Command\Config;

use Symfony\Component\Validator\Constraints\Url;

class ConfigExtUrlCommand
{
    #[Url()]
    public $url;
}
