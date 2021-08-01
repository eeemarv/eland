<?php declare(strict_types=1);

namespace App\Command\SendMessage;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class SendMessageCCCommand implements CommandInterface
{
    public $message;
    public $cc;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('message', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Length(['min' => 10, 'max' => 5000]),
            ],
        ]));
    }
}
