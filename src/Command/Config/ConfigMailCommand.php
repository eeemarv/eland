<?php declare(strict_types=1);

namespace App\Command\Config;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class ConfigMailCommand
{
    public $enabled;
    public $tag;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('enabled', new Type('bool'));

        $metadata->addPropertyConstraint('tag', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Length(['max' => 20]),
            ],
        ]));
    }
}
