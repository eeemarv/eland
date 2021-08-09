<?php declare(strict_types=1);

namespace App\Command\Mollie;

use App\Validator\Mollie\IsMollieApikey;
use App\Attributes\ConfigMap;
use App\Command\CommandInterface;

class MollieConfigCommand implements CommandInterface
{
    #[IsMollieApikey()]
    #[ConfigMap(type: 'str', key: 'mollie.apikey')]
    public $apikey;
}
