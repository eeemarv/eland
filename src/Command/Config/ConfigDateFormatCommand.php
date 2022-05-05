<?php declare(strict_types=1);

namespace App\Command\Config;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;

class ConfigDateFormatCommand implements CommandInterface
{
    #[Sequentially(constraints: [
        new NotNull(),
        new NotBlank(),
    ])]
    #[ConfigMap(type: 'str', key: 'system.date_format')]
    public $date_format;
}
