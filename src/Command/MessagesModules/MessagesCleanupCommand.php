<?php declare(strict_types=1);

namespace App\Command\MessagesModules;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class MessagesCleanupCommand
{
    public $system_name;
    public $email_tag;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('system_name', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Length(['max' => 60]),
            ],
        ]));
        $metadata->addPropertyConstraint('email_tag', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Length(['max' => 20]),
            ],
        ]));
    }
}
