<?php declare(strict_types=1);

namespace App\Command\Config;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class ConfigNameCommand implements CommandInterface
{
    public $system_name;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('system_name', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Length(['max' => 60]),
            ],
        ]));
    }
}
